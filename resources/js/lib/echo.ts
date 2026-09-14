import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'>;
    }
}

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbPort = import.meta.env.VITE_REVERB_PORT ?? 8080;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';

let echoInstance: Echo<'reverb'> | null = null;

/**
 * The socket is opened lazily and only in a browser. Session pages render on the
 * server first, and a live connection is not something the server can hold.
 */
export function getEcho(): Echo<'reverb'> | null {
    if (!reverbKey || typeof window === 'undefined') {
        return null;
    }

    if (echoInstance === null) {
        window.Pusher = Pusher;

        const reverbHost = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;

        echoInstance = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: reverbHost,
            wsPort: Number(reverbPort),
            wssPort: Number(reverbPort),
            forceTLS: reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            },
        });

        window.Echo = echoInstance;
    }

    return echoInstance;
}

export function subscribeToSession(
    sessionId: string,
    handlers: {
        onSessionState?: (payload: Record<string, unknown>) => void;
        onAttendance?: (payload: Record<string, unknown>) => void;
        onMotion?: (payload: Record<string, unknown>) => void;
        onVotingOpened?: (payload: Record<string, unknown>) => void;
        onVoteCast?: (payload: Record<string, unknown>) => void;
        onVotingClosed?: (payload: Record<string, unknown>) => void;
        onAgendaItem?: (payload: Record<string, unknown>) => void;
        onHallDisplay?: (payload: Record<string, unknown>) => void;
        onHallDisplayView?: (payload: Record<string, unknown>) => void;
        onRecognition?: (payload: Record<string, unknown>) => void;
    },
): () => void {
    const echo = getEcho();

    if (!echo) {
        return () => undefined;
    }

    const channel = echo.private(`session.${sessionId}`);

    if (handlers.onSessionState) {
        channel.listen('.SessionStateChanged', handlers.onSessionState);
    }
    if (handlers.onAttendance) {
        channel.listen('.AttendanceUpdated', handlers.onAttendance);
    }
    if (handlers.onMotion) {
        channel.listen('.MotionRecorded', handlers.onMotion);
    }
    if (handlers.onVotingOpened) {
        channel.listen('.VotingOpened', handlers.onVotingOpened);
    }
    if (handlers.onVoteCast) {
        channel.listen('.VoteCast', handlers.onVoteCast);
    }
    if (handlers.onVotingClosed) {
        channel.listen('.VotingClosed', handlers.onVotingClosed);
    }
    if (handlers.onAgendaItem) {
        channel.listen('.AgendaItemChanged', handlers.onAgendaItem);
    }
    if (handlers.onHallDisplay) {
        channel.listen('.HallDisplayChanged', handlers.onHallDisplay);
    }
    if (handlers.onHallDisplayView) {
        channel.listen('.HallDisplayViewChanged', handlers.onHallDisplayView);
    }
    if (handlers.onRecognition) {
        channel.listen('.FloorRecognitionUpdated', handlers.onRecognition);
    }

    return () => {
        echo.leave(`session.${sessionId}`);
    };
}

export type TranscriptSegment = {
    index: number;
    start: number;
    end: number;
    speaker?: string | null;
    speaker_id?: string | null;
    attributed?: boolean;
    text: string;
    confidence?: number | null;
    language?: string | null;
};

export function sortTranscriptSegments(segments: TranscriptSegment[]): TranscriptSegment[] {
    return [...segments].sort((left, right) => {
        if (left.start !== right.start) {
            return left.start - right.start;
        }

        return left.index - right.index;
    });
}

export function subscribeToTranscript(
    sessionId: string,
    handlers: {
        onSegment?: (payload: { segment: TranscriptSegment; transcript_id: string }) => void;
        onUpdated?: (payload: {
            transcript_id: string;
            status: string;
            error?: string | null;
            segments?: TranscriptSegment[] | null;
            full_text?: string | null;
        }) => void;
    },
): () => void {
    const echo = getEcho();

    if (!echo) {
        return () => undefined;
    }

    const channel = echo.private(`session-transcript.${sessionId}`);

    if (handlers.onSegment) {
        channel.listen('.TranscriptSegmentReceived', (payload: Record<string, unknown>) => {
            handlers.onSegment?.({
                segment: payload.segment as TranscriptSegment,
                transcript_id: String(payload.transcript_id ?? ''),
            });
        });
    }

    if (handlers.onUpdated) {
        channel.listen('.TranscriptUpdated', (payload: Record<string, unknown>) => {
            handlers.onUpdated?.({
                transcript_id: String(payload.transcript_id ?? ''),
                status: String(payload.status ?? ''),
                error: typeof payload.error === 'string' ? payload.error : payload.error == null ? null : String(payload.error),
                segments: (payload.segments as TranscriptSegment[] | null | undefined) ?? null,
                full_text: (payload.full_text as string | null | undefined) ?? null,
            });
        });
    }

    return () => {
        echo.leave(`session-transcript.${sessionId}`);
    };
}
