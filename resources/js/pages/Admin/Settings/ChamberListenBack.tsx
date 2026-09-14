import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Notice } from '@/components/ui/notice';
import {
    audioConstraints,
    audioContextCtor,
    canCaptureAudio,
    deviceIdFor,
    encodeWav,
    mediaErrorCode,
    mergeFloat32,
} from '@/lib/audio';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Circle, Play, RotateCcw, Square } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const MAX_MS = 8000;
const PLAYBACK_GAP_MS = 400;

type Phase = 'idle' | 'recording' | 'ready' | 'playing';
type ListenError = 'denied' | 'insecure' | 'preview' | 'failed' | 'unsupported' | 'play_failed';

type Props = {
    devices: { id: string; name: string }[];
    selectedName: string;
};

function formatElapsed(ms: number): string {
    const seconds = Math.min(Math.floor(MAX_MS / 1000), Math.max(0, Math.floor(ms / 1000)));

    return `0:${String(seconds).padStart(2, '0')}`;
}

export function ChamberListenBack({ devices, selectedName }: Props) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);
    const [phase, setPhase] = useState<Phase>('idle');
    const [error, setError] = useState<ListenError | null>(null);
    const [clipUrl, setClipUrl] = useState<string | null>(null);
    const [level, setLevel] = useState(0);
    const [elapsed, setElapsed] = useState(0);

    const streamRef = useRef<MediaStream | null>(null);
    const chunksRef = useRef<Float32Array[]>([]);
    const pcmRef = useRef<Float32Array | null>(null);
    const sampleRateRef = useRef(48000);
    const urlRef = useRef<string | null>(null);
    const timerRef = useRef(0);
    const gapRef = useRef(0);
    const contextRef = useRef<AudioContext | null>(null);
    const processorRef = useRef<ScriptProcessorNode | null>(null);
    const playbackContextRef = useRef<AudioContext | null>(null);
    const bufferSourceRef = useRef<AudioBufferSourceNode | null>(null);
    const audioRef = useRef<HTMLAudioElement | null>(null);

    function revokeClip() {
        stopBufferPlayback();

        if (urlRef.current) {
            URL.revokeObjectURL(urlRef.current);
            urlRef.current = null;
        }

        pcmRef.current = null;
        setClipUrl(null);
    }

    function stopStream() {
        streamRef.current?.getTracks().forEach((track) => {
            track.stop();
        });
        streamRef.current = null;
    }

    function stopCaptureGraph() {
        const processor = processorRef.current;
        processorRef.current = null;

        if (processor) {
            processor.onaudioprocess = null;
            processor.disconnect();
        }

        const context = contextRef.current;
        contextRef.current = null;

        if (context && context.state !== 'closed') {
            void context.close();
        }

        setLevel(0);
    }

    function stopTimer() {
        if (timerRef.current) {
            window.clearInterval(timerRef.current);
            timerRef.current = 0;
        }

        if (gapRef.current) {
            window.clearTimeout(gapRef.current);
            gapRef.current = 0;
        }
    }

    function stopBufferPlayback() {
        const source = bufferSourceRef.current;
        bufferSourceRef.current = null;

        if (source) {
            source.onended = null;

            try {
                source.stop();
            } catch {
                // already stopped
            }
        }

        const context = playbackContextRef.current;
        playbackContextRef.current = null;

        if (context && context.state !== 'closed') {
            void context.close();
        }
    }

    function stopPlayback() {
        const audio = audioRef.current;

        if (audio) {
            audio.pause();
            audio.currentTime = 0;
        }

        stopBufferPlayback();
    }

    function teardown() {
        stopTimer();
        stopCaptureGraph();
        stopStream();
        stopPlayback();
        revokeClip();
        setPhase('idle');
        setError(null);
        setElapsed(0);
    }

    useEffect(() => {
        return () => {
            teardown();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function finishClip() {
        stopCaptureGraph();
        stopStream();

        const samples = mergeFloat32(chunksRef.current);
        chunksRef.current = [];

        if (samples.length < sampleRateRef.current / 10) {
            setError('failed');
            setPhase('idle');
            return;
        }

        pcmRef.current = samples;
        const blob = encodeWav(samples, sampleRateRef.current);
        const url = URL.createObjectURL(blob);
        urlRef.current = url;

        gapRef.current = window.setTimeout(() => {
            gapRef.current = 0;
            setClipUrl(url);
            setPhase('ready');
        }, PLAYBACK_GAP_MS);
    }

    async function startRecording() {
        setError(null);
        stopPlayback();
        revokeClip();
        setElapsed(0);
        setLevel(0);

        const blocked = canCaptureAudio();

        if (blocked) {
            setError(blocked === 'empty' ? 'failed' : blocked);
            return;
        }

        const Ctor = audioContextCtor();

        if (!Ctor) {
            setError('preview');
            return;
        }

        let stream: MediaStream;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: audioConstraints(deviceIdFor(devices, selectedName)),
            });
        } catch (caught) {
            const code = mediaErrorCode(caught);
            setError(code === 'empty' ? 'failed' : code);

            return;
        }

        const context = new Ctor();

        if (context.state === 'suspended') {
            await context.resume();
        }

        if (typeof context.createScriptProcessor !== 'function') {
            stream.getTracks().forEach((track) => track.stop());
            void context.close();
            setError('unsupported');
            return;
        }

        streamRef.current = stream;
        contextRef.current = context;
        chunksRef.current = [];
        sampleRateRef.current = context.sampleRate;

        const source = context.createMediaStreamSource(stream);
        const processor = context.createScriptProcessor(4096, 1, 1);
        const mute = context.createGain();
        mute.gain.value = 0;
        processorRef.current = processor;

        processor.onaudioprocess = (event) => {
            const input = event.inputBuffer.getChannelData(0);
            chunksRef.current.push(new Float32Array(input));

            let sum = 0;

            for (const sample of input) {
                sum += sample * sample;
            }

            setLevel(Math.min(1, Math.sqrt(sum / input.length) * 4));
        };

        source.connect(processor);
        processor.connect(mute);
        mute.connect(context.destination);

        setPhase('recording');

        const startedAt = Date.now();
        timerRef.current = window.setInterval(() => {
            const next = Date.now() - startedAt;
            setElapsed(next);

            if (next >= MAX_MS) {
                stopRecording();
            }
        }, 100);
    }

    function stopRecording() {
        if (processorRef.current === null) {
            return;
        }

        stopTimer();
        finishClip();
    }

    async function playClip() {
        const samples = pcmRef.current;
        const Ctor = audioContextCtor();

        if (!samples || !Ctor) {
            setError('play_failed');
            return;
        }

        setError(null);
        stopPlayback();

        try {
            const context = new Ctor();
            playbackContextRef.current = context;

            if (context.state === 'suspended') {
                await context.resume();
            }

            const buffer = context.createBuffer(1, samples.length, sampleRateRef.current);
            buffer.getChannelData(0).set(samples);
            const source = context.createBufferSource();
            bufferSourceRef.current = source;
            source.buffer = buffer;
            source.connect(context.destination);
            source.onended = () => {
                bufferSourceRef.current = null;
                setPhase('ready');
                void context.close();
                playbackContextRef.current = null;
            };
            source.start();
            setPhase('playing');
        } catch {
            stopBufferPlayback();
            setError('play_failed');
            setPhase('ready');
        }
    }

    function stopClip() {
        stopPlayback();
        setPhase('ready');
    }

    const usingLabel =
        selectedName !== '' ? t('chamber.listen.using', { name: selectedName }) : t('chamber.listen.using_default');
    const statusKey =
        phase === 'recording'
            ? 'chamber.listen.recording'
            : phase === 'playing'
              ? 'chamber.listen.playing'
              : phase === 'ready'
                ? 'chamber.listen.recorded'
                : 'chamber.listen.ready';

    return (
        <>
            <Button type="button" variant="secondary" size="sm" onClick={() => setOpen(true)}>
                {t('chamber.listen')}
            </Button>
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (!next) {
                        teardown();
                    }

                    setOpen(next);
                }}
            >
                <DialogContent title={t('chamber.listen.title')} description={t('chamber.listen.hint')}>
                    <div className="flex flex-col gap-3">
                        <p className="text-sm text-ink">{usingLabel}</p>
                        <p className="text-sm text-ink-muted">{t(statusKey)}</p>
                        {error ? (
                            <Notice tone="caution">
                                {error === 'denied' || error === 'insecure' || error === 'preview'
                                    ? t(`chamber.devices.${error}`)
                                    : t(`chamber.listen.${error}`)}
                            </Notice>
                        ) : null}

                        <div className="flex items-center gap-3">
                            <span className="block h-1.5 w-full overflow-hidden rounded-full bg-canvas-sunk" aria-hidden="true">
                                <span
                                    className={cn(
                                        'block h-full rounded-full transition-[width] duration-100',
                                        phase === 'recording' ? 'bg-live' : 'bg-accent',
                                    )}
                                    style={{
                                        width: `${Math.round((phase === 'recording' ? level : 0) * 100)}%`,
                                    }}
                                />
                            </span>
                            <span className="shrink-0 font-mono text-xs text-ink-muted">
                                {formatElapsed(elapsed)} / {formatElapsed(MAX_MS)}
                            </span>
                        </div>

                        {clipUrl ? (
                            <audio
                                key={clipUrl}
                                ref={audioRef}
                                controls
                                preload="auto"
                                playsInline
                                className="h-10 w-full"
                                src={clipUrl}
                            />
                        ) : null}

                        <div className="flex flex-wrap gap-2">
                            {phase === 'recording' ? (
                                <Button type="button" variant="live" size="sm" onClick={stopRecording}>
                                    <Square aria-hidden="true" strokeWidth={1.75} />
                                    {t('chamber.listen.stop')}
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant={phase === 'ready' || phase === 'playing' ? 'secondary' : 'live'}
                                    size="sm"
                                    onClick={() => void startRecording()}
                                    disabled={phase === 'playing'}
                                >
                                    {phase === 'ready' ? (
                                        <RotateCcw aria-hidden="true" strokeWidth={1.75} />
                                    ) : (
                                        <Circle aria-hidden="true" strokeWidth={1.75} />
                                    )}
                                    {phase === 'ready' ? t('chamber.listen.again') : t('chamber.listen.record')}
                                </Button>
                            )}
                            {clipUrl && phase !== 'recording' ? (
                                phase === 'playing' ? (
                                    <Button type="button" variant="secondary" size="sm" onClick={stopClip}>
                                        <Square aria-hidden="true" strokeWidth={1.75} />
                                        {t('chamber.listen.stop_play')}
                                    </Button>
                                ) : (
                                    <Button type="button" variant="primary" size="sm" onClick={() => void playClip()}>
                                        <Play aria-hidden="true" strokeWidth={1.75} />
                                        {t('chamber.listen.play')}
                                    </Button>
                                )
                            ) : null}
                        </div>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="ghost" size="sm">
                                {t('actions.close')}
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
