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
        onGuests?: (payload: Record<string, unknown>) => void;
        onMotion?: (payload: Record<string, unknown>) => void;
        onVotingOpened?: (payload: Record<string, unknown>) => void;
        onVoteCast?: (payload: Record<string, unknown>) => void;
        onVotingClosed?: (payload: Record<string, unknown>) => void;
        onAgendaItem?: (payload: Record<string, unknown>) => void;
        onHallDisplay?: (payload: Record<string, unknown>) => void;
        onHallDisplayView?: (payload: Record<string, unknown>) => void;
        onRecognition?: (payload: Record<string, unknown>) => void;
        onMinutesCorrections?: (payload: Record<string, unknown>) => void;
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
    if (handlers.onGuests) {
        channel.listen('.SessionGuestsUpdated', handlers.onGuests);
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
    if (handlers.onMinutesCorrections) {
        channel.listen('.MinutesCorrectionsChanged', handlers.onMinutesCorrections);
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
    original_text?: string;
    original_speaker?: string | null;
    original_speaker_id?: string | null;
    original_attributed?: boolean;
    is_edited?: boolean;
    edited_at?: string | null;
    edited_by?: string | null;
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

export type SessionChatMessage = {
    id: string;
    user_id: string;
    display_name: string | null;
    body: string;
    created_at: string | null;
};

export type SessionChatParticipant = {
    id: string;
    display_name: string | null;
    is_clerk: boolean;
    last_read_at?: string | null;
};

export type SessionChatPerson = {
    id: string;
    display_name: string;
    is_clerk: boolean;
    is_designated_secretary?: boolean;
};

export type SessionChatConversation = {
    id: string;
    type: 'direct' | 'group';
    name: string | null;
    title: string;
    created_by: string;
    is_creator: boolean;
    participants: SessionChatParticipant[];
    last_message: {
        id: string;
        user_id: string;
        body: string;
        created_at: string | null;
    } | null;
    last_message_at: string | null;
    unread_count: number;
    messages?: SessionChatMessage[];
};

export type SessionChatReadReceipt = {
    conversation_id: string;
    session_id: string;
    user_id: string;
    last_read_at: string | null;
};

export function subscribeToSessionChatInbox(
    userId: string,
    onUpdate: (payload: { conversation_id: string; session_id: string }) => void,
): () => void {
    const echo = getEcho();

    if (!echo) {
        return () => undefined;
    }

    const channel = echo.private(`App.Models.User.${userId}`);

    const handler = (payload: Record<string, unknown>) => {
        onUpdate({
            conversation_id: String(payload.conversation_id ?? ''),
            session_id: String(payload.session_id ?? ''),
        });
    };

    channel.listen('.SessionChatInboxUpdated', handler);

    return () => {
        channel.stopListening('.SessionChatInboxUpdated', handler);
    };
}

export function subscribeToSessionChatThread(
    conversationId: string,
    handlers: {
        onMessage: (payload: { conversation_id: string; session_id: string; message: SessionChatMessage }) => void;
        onRead?: (payload: SessionChatReadReceipt) => void;
    },
): () => void {
    const echo = getEcho();

    if (!echo) {
        return () => undefined;
    }

    const channel = echo.private(`session-chat.${conversationId}`);

    const messageHandler = (payload: Record<string, unknown>) => {
        const message = payload.message as SessionChatMessage | undefined;

        if (!message?.id) {
            return;
        }

        handlers.onMessage({
            conversation_id: String(payload.conversation_id ?? conversationId),
            session_id: String(payload.session_id ?? ''),
            message,
        });
    };

    channel.listen('.SessionChatMessageSent', messageHandler);

    const readHandler = (payload: Record<string, unknown>) => {
        handlers.onRead?.({
            conversation_id: String(payload.conversation_id ?? conversationId),
            session_id: String(payload.session_id ?? ''),
            user_id: String(payload.user_id ?? ''),
            last_read_at: typeof payload.last_read_at === 'string' ? payload.last_read_at : null,
        });
    };

    if (handlers.onRead) {
        channel.listen('.SessionChatRead', readHandler);
    }

    return () => {
        echo.leave(`session-chat.${conversationId}`);
    };
}
