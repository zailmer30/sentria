import { subscribeToSession } from '@/lib/echo';
import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type SessionEchoHandlers = {
    /** Zoom/scroll — applied locally, never reloads. */
    onHallDisplayView?: (payload: Record<string, unknown>) => void;
    /**
     * Document project/hide. When provided, the dashboard applies the stage
     * immediately from the event instead of waiting on an Inertia reload.
     */
    onHallDisplay?: (payload: Record<string, unknown>) => void;
    /** Fired as soon as voting opens, before the props reload finishes. */
    onVotingOpened?: (payload: Record<string, unknown>) => void;
    /** Fired as soon as voting closes, before the props reload finishes. */
    onVotingClosed?: (payload: Record<string, unknown>) => void;
    /** Live tallies without waiting for a full voting props reload. */
    onVoteCast?: (payload: Record<string, unknown>) => void;
    /** Floor recognition queue — hall board paints immediately. */
    onRecognition?: (payload: Record<string, unknown>) => void;
};

/**
 * Reload floor props when live session events arrive over Reverb.
 * Optional handlers let the hall board react before the Inertia round-trip.
 */
export function useSessionEcho(
    sessionId: string,
    only: string[] = ['session', 'current_item', 'next_item', 'previous_item', 'quorum', 'attendance', 'voting', 'motions', 'elapsed_seconds'],
    handlers: SessionEchoHandlers | ((payload: Record<string, unknown>) => void) = {},
) {
    const handlersRef = useRef<SessionEchoHandlers>({});

    handlersRef.current = typeof handlers === 'function' ? { onHallDisplayView: handlers } : handlers;

    useEffect(() => {
        const hallProps = only.includes('hall_display')
            ? (['hall_display', 'reading_pack', 'voting', 'current_item'] as const)
            : (['hall_display'] as const);

        const unsubscribe = subscribeToSession(sessionId, {
            onSessionState: () => router.reload({ only }),
            onAttendance: () => router.reload({ only: ['quorum', 'attendance'] }),
            onMotion: () => router.reload({ only: ['motions', 'recognition', 'can'] }),
            onVotingOpened: (payload) => {
                handlersRef.current.onVotingOpened?.(payload);
                router.reload({
                    only: only.includes('hall_display')
                        ? ['voting', 'current_item', 'hall_display']
                        : ['voting', 'current_item'],
                });
            },
            onVoteCast: (payload) => {
                handlersRef.current.onVoteCast?.(payload);
                router.reload({ only: ['voting'] });
            },
            onVotingClosed: (payload) => {
                handlersRef.current.onVotingClosed?.(payload);
                router.reload({
                    only: only.includes('hall_display')
                        ? ['voting', 'motions', 'current_item', 'calendar_docket', 'reading_pack', 'advance_blocked_reason', 'hall_display']
                        : ['voting', 'motions', 'current_item', 'calendar_docket', 'reading_pack', 'advance_blocked_reason'],
                });
            },
            onAgendaItem: () =>
                router.reload({
                    only: [
                        'current_item',
                        'next_item',
                        'previous_item',
                        'session',
                        'reading_pack',
                        'calendar_docket',
                        'document_link',
                        'private_notes',
                        ...(only.includes('hall_display') ? (['hall_display'] as const) : []),
                    ],
                }),
            onHallDisplay: (payload) => {
                if (handlersRef.current.onHallDisplay) {
                    handlersRef.current.onHallDisplay(payload);

                    return;
                }

                router.reload({ only: [...hallProps] });
            },
            onHallDisplayView: (payload) => handlersRef.current.onHallDisplayView?.(payload),
            onRecognition: (payload) => {
                if (handlersRef.current.onRecognition) {
                    handlersRef.current.onRecognition(payload);

                    return;
                }

                router.reload({ only: ['recognition', 'can'] });
            },
        });

        return unsubscribe;
    }, [sessionId, only]);
}
