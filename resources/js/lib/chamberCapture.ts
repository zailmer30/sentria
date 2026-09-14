import {
    audioConstraints,
    audioContextCtor,
    canCaptureAudio,
    deviceIdFor,
    encodeWav,
    mediaErrorCode,
    toLocalDevices,
    type LocalDevice,
    type MediaErrorCode,
} from '@/lib/audio';

/**
 * Chamber capture engine.
 *
 * This deliberately lives outside React. A sitting is recorded for hours while
 * the clerk moves between the console, the minutes tab and the transcript, and
 * Inertia unmounts the page component on every one of those moves. Holding the
 * audio graph in a page's `useEffect` meant navigating away silently ended the
 * recording, so the graph, the upload queue and the sequence cursor all belong
 * to the module instead, and React only subscribes to a snapshot of it.
 *
 * Only an explicit stop, or a full document reload, ends a recording.
 */

const WORKLET_URL = '/audio/chamber-vad.worklet.js';
const MIXER_CHANNEL = 1;
const STATE_POLL_MS = 3000;
const HEARTBEAT_MS = 1000;
const UPLOAD_ATTEMPTS = 5;
const QUEUE_LIMIT = 32;

export type CaptureMode = 'idle' | 'record' | 'pause' | 'disabled';

export type CaptureState = {
    session_id: string | null;
    status: string;
    status_label?: string;
    capture: CaptureMode;
    feed: string;
    device: { index: number | null; name: string | null } | null;
    recording_enabled: boolean | null;
    epoch_ms: number | null;
    transcript_id?: string | null;
    channels: { channel_index: number; next_seq?: number }[];
};

export type CaptureTuning = {
    sample_rate: number;
    vad_rms: number;
    min_speech_ms: number;
    silence_end_ms: number;
    max_utterance_ms: number;
};

export type CapturePhase = 'stopped' | 'starting' | 'listening';

export type CaptureError = MediaErrorCode | 'unsupported' | 'worklet';

export type CaptureStats = {
    uploaded: number;
    skipped: number;
    failed: number;
    queued: number;
};

export type CaptureSnapshot = {
    phase: CapturePhase;
    /** The sitting being recorded, which may not be the one on screen. */
    sessionId: string | null;
    sessionLabel: string;
    state: CaptureState | null;
    error: CaptureError | null;
    stats: CaptureStats;
    devices: LocalDevice[];
    deviceId: string;
    deviceLabel: string;
};

type PendingChunk = {
    seq: number;
    startedAtMs: number;
    endedAtMs: number;
    blob: Blob;
};

type UtteranceMessage = {
    type: 'utterance';
    pcm: Float32Array;
    startedAtMs: number;
    endedAtMs: number;
};

type LevelMessage = {
    type: 'level';
    rms: number;
};

type WorkletMessage = UtteranceMessage | LevelMessage;

const EMPTY_STATS: CaptureStats = { uploaded: 0, skipped: 0, failed: 0, queued: 0 };

const INITIAL_SNAPSHOT: CaptureSnapshot = {
    phase: 'stopped',
    sessionId: null,
    sessionLabel: '',
    state: null,
    error: null,
    stats: EMPTY_STATS,
    devices: [],
    deviceId: '',
    deviceLabel: '',
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

class ChamberCaptureEngine {
    private listeners = new Set<() => void>();

    private snapshot: CaptureSnapshot = INITIAL_SNAPSHOT;

    private stream: MediaStream | null = null;

    private context: AudioContext | null = null;

    private node: AudioWorkletNode | null = null;

    private wakeLock: WakeLockSentinel | null = null;

    private tuning: CaptureTuning | null = null;

    private queue: PendingChunk[] = [];

    private pumping = false;

    private seq = 0;

    private startedWallMs = 0;

    /**
     * Peak RMS from the worklet. Kept off the snapshot on purpose: it changes
     * about eleven times a second, and pushing that through the store would
     * re-render every subscriber for a moving bar nobody else is watching.
     */
    private level = 0;

    private pollTimer = 0;

    private heartbeatTimer = 0;

    /** Pages currently showing capture UI for the active sitting. */
    private attached = 0;

    private globalsInstalled = false;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => {
            this.listeners.delete(listener);
        };
    };

    getSnapshot = (): CaptureSnapshot => this.snapshot;

    getServerSnapshot = (): CaptureSnapshot => INITIAL_SNAPSHOT;

    getLevel = (): number => this.level;

    isRecording(): boolean {
        return this.snapshot.phase === 'listening';
    }

    private emit(patch: Partial<CaptureSnapshot>): void {
        this.snapshot = { ...this.snapshot, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    private installGlobals(): void {
        if (this.globalsInstalled || typeof window === 'undefined') {
            return;
        }

        this.globalsInstalled = true;

        window.addEventListener('beforeunload', (event) => {
            if (!this.isRecording()) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });

        // Screen wake locks are dropped whenever the tab is backgrounded, so a
        // sitting that runs behind another window has to reclaim it on return.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible' || !this.isRecording() || this.wakeLock) {
                return;
            }

            navigator.wakeLock
                ?.request('screen')
                .then((lock) => {
                    this.wakeLock = lock;
                })
                .catch(() => undefined);
        });
    }

    /**
     * Bind a page to a sitting. While a recording is running the active sitting
     * is fixed, so opening the console for a different sitting shows its status
     * rather than quietly retargeting the microphone.
     */
    attach(sessionId: string, sessionLabel: string, initialState: CaptureState): () => void {
        this.installGlobals();
        this.attached += 1;

        if (this.snapshot.phase === 'stopped' && this.snapshot.sessionId !== sessionId) {
            this.queue = [];
            this.seq = 0;
            this.emit({
                sessionId,
                sessionLabel,
                state: initialState,
                stats: EMPTY_STATS,
                error: null,
            });
        } else if (this.snapshot.state === null) {
            this.emit({ state: initialState });
        }

        this.syncTimers();

        return () => {
            this.attached = Math.max(0, this.attached - 1);
            this.syncTimers();
        };
    }

    private syncTimers(): void {
        const wantPoll = this.snapshot.sessionId !== null && (this.attached > 0 || this.snapshot.phase !== 'stopped');

        if (wantPoll && this.pollTimer === 0) {
            void this.poll();
            this.pollTimer = window.setInterval(() => void this.poll(), STATE_POLL_MS);
        } else if (!wantPoll && this.pollTimer !== 0) {
            window.clearInterval(this.pollTimer);
            this.pollTimer = 0;
        }

        const wantHeartbeat = this.isRecording();

        if (wantHeartbeat && this.heartbeatTimer === 0) {
            this.heartbeatTimer = window.setInterval(() => this.heartbeat(), HEARTBEAT_MS);
        } else if (!wantHeartbeat && this.heartbeatTimer !== 0) {
            window.clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = 0;
        }
    }

    private async poll(): Promise<void> {
        const sessionId = this.snapshot.sessionId;

        if (sessionId === null) {
            return;
        }

        try {
            const response = await fetch(`/sessions/${sessionId}/capture/state`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as CaptureState;

            // The server owns the cursor: it survives a reload, a second
            // operator taking over, and a daemon that recorded earlier.
            const channel = payload.channels?.find((row) => row.channel_index === MIXER_CHANNEL);
            const nextSeq = Math.max(0, channel?.next_seq ?? 0);

            if (nextSeq > this.seq) {
                this.seq = nextSeq;
            }

            this.emit({ state: payload });
        } catch {
            // Keep the last known state; the next tick will correct it.
        }
    }

    private heartbeat(): void {
        const sessionId = this.snapshot.sessionId;

        if (sessionId === null) {
            return;
        }

        void fetch(`/sessions/${sessionId}/capture/heartbeat`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                device: this.snapshot.deviceLabel.slice(0, 120) || 'Browser capture',
                channel_rms: { [MIXER_CHANNEL]: this.level },
                listening_inputs: 1,
            }),
        }).catch(() => undefined);
    }

    async refreshDevices(unnamed: string): Promise<LocalDevice[]> {
        if (typeof navigator === 'undefined' || !navigator.mediaDevices?.enumerateDevices) {
            return [];
        }

        try {
            const listed = await navigator.mediaDevices.enumerateDevices();
            const inputs = toLocalDevices(listed, unnamed);
            this.emit({ devices: inputs });

            return inputs;
        } catch {
            return [];
        }
    }

    private bumpStats(patch: Partial<CaptureStats>): void {
        const current = this.snapshot.stats;

        this.emit({
            stats: {
                uploaded: current.uploaded + (patch.uploaded ?? 0),
                skipped: current.skipped + (patch.skipped ?? 0),
                failed: current.failed + (patch.failed ?? 0),
                queued: patch.queued ?? current.queued,
            },
        });
    }

    /**
     * One attempt chain per chunk, mirroring the daemon: retry transport and
     * server faults, but drop on a refusal (adjourned, suspended, recording
     * off) because replaying it later would only be refused again.
     */
    private async sendChunk(sessionId: string, chunk: PendingChunk): Promise<'sent' | 'skipped' | 'failed'> {
        for (let attempt = 0; attempt < UPLOAD_ATTEMPTS; attempt += 1) {
            try {
                const body = new FormData();
                body.append('channel_index', String(MIXER_CHANNEL));
                body.append('seq', String(chunk.seq));
                body.append('started_at_ms', String(chunk.startedAtMs));
                body.append('ended_at_ms', String(chunk.endedAtMs));
                body.append('audio', chunk.blob, 'chunk.wav');

                const response = await fetch(`/sessions/${sessionId}/capture/chunks`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });

                if (response.ok) {
                    const payload = (await response.json().catch(() => null)) as { skipped?: boolean } | null;

                    return payload?.skipped ? 'skipped' : 'sent';
                }

                if (response.status < 500) {
                    return 'skipped';
                }
            } catch {
                // Network fault: fall through to the backoff below.
            }

            await new Promise((resolve) => window.setTimeout(resolve, 2 ** attempt * 1000));
        }

        return 'failed';
    }

    private async pump(sessionId: string): Promise<void> {
        if (this.pumping) {
            return;
        }

        this.pumping = true;

        while (this.queue.length > 0) {
            const chunk = this.queue[0];

            if (!chunk) {
                break;
            }

            const outcome = await this.sendChunk(sessionId, chunk);
            this.queue.shift();

            this.bumpStats({
                uploaded: outcome === 'sent' ? 1 : 0,
                skipped: outcome === 'skipped' ? 1 : 0,
                failed: outcome === 'failed' ? 1 : 0,
                queued: this.queue.length,
            });
        }

        this.pumping = false;
    }

    private enqueue(message: UtteranceMessage, sampleRate: number): void {
        const sessionId = this.snapshot.sessionId;
        const state = this.snapshot.state;

        if (sessionId === null || state === null || state.capture !== 'record' || state.session_id !== sessionId) {
            return;
        }

        // Worklet timestamps are relative to when the stream opened; the
        // transcript wants them relative to when the sitting was called to
        // order, which may have happened after the operator hit Start.
        const offset = this.startedWallMs - (state.epoch_ms ?? 0);
        const startedAtMs = Math.max(0, Math.round(offset + message.startedAtMs));
        const endedAtMs = Math.max(startedAtMs, Math.round(offset + message.endedAtMs));

        this.queue.push({
            seq: this.seq,
            startedAtMs,
            endedAtMs,
            blob: encodeWav(message.pcm, sampleRate),
        });

        this.seq += 1;

        if (this.queue.length > QUEUE_LIMIT) {
            this.queue.shift();
            this.bumpStats({ failed: 1 });
        }

        this.bumpStats({ queued: this.queue.length });
        void this.pump(sessionId);
    }

    async start({
        sessionId,
        sessionLabel,
        tuning,
        preferredDeviceName,
        deviceId,
    }: {
        sessionId: string;
        sessionLabel: string;
        tuning: CaptureTuning;
        preferredDeviceName: string;
        deviceId?: string;
    }): Promise<void> {
        if (this.snapshot.phase !== 'stopped') {
            return;
        }

        this.installGlobals();
        this.emit({ error: null, sessionId, sessionLabel });

        const blocked = canCaptureAudio();

        if (blocked) {
            this.emit({ error: blocked });
            return;
        }

        const Ctor = audioContextCtor();

        if (!Ctor) {
            this.emit({ error: 'preview' });
            return;
        }

        this.tuning = tuning;
        this.emit({ phase: 'starting' });

        let stream: MediaStream;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: audioConstraints(deviceId ?? deviceIdFor(this.snapshot.devices, preferredDeviceName), 1),
            });
        } catch (caught) {
            this.emit({ error: mediaErrorCode(caught), phase: 'stopped' });
            return;
        }

        let context: AudioContext;

        try {
            context = new Ctor({ sampleRate: tuning.sample_rate });
        } catch {
            context = new Ctor();
        }

        try {
            if (context.state === 'suspended') {
                await context.resume();
            }

            if (!context.audioWorklet) {
                throw new Error('AudioWorklet unavailable');
            }

            await context.audioWorklet.addModule(WORKLET_URL);
        } catch {
            stream.getTracks().forEach((track) => track.stop());
            void context.close();
            this.emit({ error: 'unsupported', phase: 'stopped' });
            return;
        }

        let node: AudioWorkletNode;

        try {
            node = new AudioWorkletNode(context, 'chamber-vad', {
                numberOfInputs: 1,
                numberOfOutputs: 1,
                outputChannelCount: [1],
                processorOptions: {
                    vadRms: tuning.vad_rms,
                    minSpeechMs: tuning.min_speech_ms,
                    silenceEndMs: tuning.silence_end_ms,
                    maxUtteranceMs: tuning.max_utterance_ms,
                },
            });
        } catch {
            stream.getTracks().forEach((track) => track.stop());
            void context.close();
            this.emit({ error: 'worklet', phase: 'stopped' });
            return;
        }

        const sampleRate = context.sampleRate;

        node.port.onmessage = (event: MessageEvent<WorkletMessage>) => {
            const message = event.data;

            if (message.type === 'level') {
                this.level = message.rms;
                return;
            }

            this.enqueue(message, sampleRate);
        };

        // Chrome only pulls a worklet that has a path to the destination, so
        // the (silent) output is parked on a muted gain node. Without it,
        // process() is never called and nothing is ever transcribed.
        const mute = context.createGain();
        mute.gain.value = 0;

        context.createMediaStreamSource(stream).connect(node);
        node.connect(mute);
        mute.connect(context.destination);

        this.stream = stream;
        this.context = context;
        this.node = node;
        this.startedWallMs = Date.now();

        this.emit({
            phase: 'listening',
            deviceId: deviceId ?? '',
            deviceLabel: stream.getAudioTracks()[0]?.label ?? '',
        });

        this.syncTimers();

        try {
            this.wakeLock = (await navigator.wakeLock?.request('screen')) ?? null;
        } catch {
            // A denied wake lock is not worth refusing to record over.
        }
    }

    stop(): void {
        const node = this.node;
        this.node = null;

        // The handler stays attached: `stop` makes the worklet flush whatever
        // it is holding, and that last utterance still has to reach the queue.
        node?.port.postMessage({ type: 'stop' });

        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;

        const context = this.context;
        this.context = null;

        if (context && context.state !== 'closed') {
            // Let the worklet's final flush land before the graph goes away.
            window.setTimeout(() => {
                void context.close();
            }, 150);
        }

        void this.wakeLock?.release().catch(() => undefined);
        this.wakeLock = null;

        this.level = 0;
        this.emit({ phase: 'stopped', deviceLabel: '' });
        this.syncTimers();
    }
}

export const chamberCapture = new ChamberCaptureEngine();
