import { enqueueVote, findQueuedVote, loadQueue, removeQueuedVote, type QueuedVote } from '@/lib/offlineVoteQueue';
import { useCallback, useEffect, useState } from 'react';
import { useOnlineStatus } from './useOnlineStatus';

type CastVotePayload = {
    sessionId: string;
    agendaItemId: string;
    votingRound: number;
    choice: string;
};

type UseOfflineVoteQueueOptions = {
    onVoteFlushed?: (payload: CastVotePayload) => void;
    onFlushError?: (payload: CastVotePayload, error: unknown) => void;
};

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function flushVote(payload: CastVotePayload): Promise<boolean> {
    const response = await fetch(`/sessions/${payload.sessionId}/voting/cast`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            agenda_item_id: payload.agendaItemId,
            choice: payload.choice,
            voting_round: payload.votingRound,
        }),
    });

    return response.ok || response.status === 200;
}

export function useOfflineVoteQueue(options: UseOfflineVoteQueueOptions = {}) {
    const online = useOnlineStatus();
    const [pending, setPending] = useState<QueuedVote[]>(() => loadQueue());
    const [flushing, setFlushing] = useState(false);

    const refresh = useCallback(() => {
        setPending(loadQueue());
    }, []);

    const queueVote = useCallback((payload: CastVotePayload) => {
        const updated = enqueueVote({
            session_id: payload.sessionId,
            agenda_item_id: payload.agendaItemId,
            voting_round: payload.votingRound,
            choice: payload.choice,
        });
        setPending(updated);

        return updated;
    }, []);

    const flushQueue = useCallback(async () => {
        if (!online || flushing) {
            return;
        }

        const queue = loadQueue();
        if (queue.length === 0) {
            return;
        }

        setFlushing(true);

        try {
            for (const vote of queue) {
                const payload: CastVotePayload = {
                    sessionId: vote.session_id,
                    agendaItemId: vote.agenda_item_id,
                    votingRound: vote.voting_round,
                    choice: vote.choice,
                };

                try {
                    const success = await flushVote(payload);
                    if (success) {
                        removeQueuedVote(vote.session_id, vote.agenda_item_id, vote.voting_round);
                        options.onVoteFlushed?.(payload);
                    }
                } catch (error) {
                    options.onFlushError?.(payload, error);
                    break;
                }
            }
        } finally {
            setFlushing(false);
            refresh();
        }
    }, [flushing, online, options, refresh]);

    useEffect(() => {
        function handleOnline(): void {
            void flushQueue();
        }

        window.addEventListener('online', handleOnline);

        const timer = window.setTimeout(() => {
            if (navigator.onLine) {
                void flushQueue();
            }
        }, 0);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.clearTimeout(timer);
        };
    }, [flushQueue]);

    const getQueuedChoice = useCallback((sessionId: string, agendaItemId: string, votingRound: number): string | null => {
        return findQueuedVote(sessionId, agendaItemId, votingRound)?.choice ?? null;
    }, []);

    return {
        online,
        pending,
        flushing,
        queueVote,
        flushQueue,
        getQueuedChoice,
        refresh,
    };
}
