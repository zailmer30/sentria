export type QueuedVote = {
    id: string;
    session_id: string;
    agenda_item_id: string;
    voting_round: number;
    choice: string;
    queued_at: string;
};

export const OFFLINE_VOTE_QUEUE_KEY = 'sentria.offline_votes';

export function voteQueueKey(sessionId: string, agendaItemId: string, votingRound: number): string {
    return `${sessionId}:${agendaItemId}:${votingRound}`;
}

export function loadQueue(): QueuedVote[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(OFFLINE_VOTE_QUEUE_KEY);
        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw) as unknown;
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.filter(isQueuedVote);
    } catch {
        return [];
    }
}

export function saveQueue(queue: QueuedVote[]): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(OFFLINE_VOTE_QUEUE_KEY, JSON.stringify(queue));
}

export function enqueueVote(vote: Omit<QueuedVote, 'id' | 'queued_at'> & { id?: string; queued_at?: string }): QueuedVote[] {
    const queue = loadQueue();
    const key = voteQueueKey(vote.session_id, vote.agenda_item_id, vote.voting_round);
    const withoutDuplicate = queue.filter(
        (entry) => voteQueueKey(entry.session_id, entry.agenda_item_id, entry.voting_round) !== key,
    );

    const next: QueuedVote = {
        id: vote.id ?? crypto.randomUUID(),
        session_id: vote.session_id,
        agenda_item_id: vote.agenda_item_id,
        voting_round: vote.voting_round,
        choice: vote.choice,
        queued_at: vote.queued_at ?? new Date().toISOString(),
    };

    const updated = [...withoutDuplicate, next];
    saveQueue(updated);

    return updated;
}

export function removeQueuedVote(sessionId: string, agendaItemId: string, votingRound: number): QueuedVote[] {
    const key = voteQueueKey(sessionId, agendaItemId, votingRound);
    const updated = loadQueue().filter(
        (entry) => voteQueueKey(entry.session_id, entry.agenda_item_id, entry.voting_round) !== key,
    );
    saveQueue(updated);

    return updated;
}

export function findQueuedVote(sessionId: string, agendaItemId: string, votingRound: number): QueuedVote | null {
    const key = voteQueueKey(sessionId, agendaItemId, votingRound);

    return loadQueue().find((entry) => voteQueueKey(entry.session_id, entry.agenda_item_id, entry.voting_round) === key) ?? null;
}

function isQueuedVote(value: unknown): value is QueuedVote {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        typeof record.id === 'string' &&
        typeof record.session_id === 'string' &&
        typeof record.agenda_item_id === 'string' &&
        typeof record.voting_round === 'number' &&
        typeof record.choice === 'string' &&
        typeof record.queued_at === 'string'
    );
}
