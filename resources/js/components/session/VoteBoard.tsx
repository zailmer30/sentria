import { UserAvatar } from '@/components/users/UserAvatar';
import { useTranslations } from '@/lib/i18n';
import { withHonorific } from '@/lib/sessionFloor';
import { cn } from '@/lib/utils';
import { User } from 'lucide-react';

/**
 * The chamber's tally board. On the floor dashboard the figures sit in ruled
 * columns with colour-coded labels. When the roster is on hand, each column
 * is the members who have taken that side — readable from a bench without
 * borrowing the national red (which stays on the voting-open state itself).
 */

export type Tallies = {
    yes: number;
    no: number;
    abstain: number;
    inhibit: number;
    total: number;
};

export type VoteKey = 'yes' | 'no' | 'abstain' | 'inhibit';

export type VoteBoardMember = {
    id: string;
    display_name: string | null;
    avatar_url: string | null;
    status: string;
    has_voted: boolean;
    choice?: string | null;
};

/** Ballot category colours — distinct from live (national red). */
export const VOTE_LABEL: Record<VoteKey, string> = {
    yes: 'text-success',
    no: 'text-critical',
    abstain: 'text-warning',
    inhibit: 'text-[var(--color-vote-inhibit)]',
};

export const VOTE_BAR: Record<VoteKey, string> = {
    yes: 'bg-success',
    no: 'bg-critical',
    abstain: 'bg-[var(--color-warning)]',
    inhibit: 'bg-[var(--color-vote-inhibit)]',
};

export const VOTE_SOFT: Record<VoteKey | 'pending', string> = {
    yes: 'bg-success-soft',
    no: 'bg-critical-soft',
    abstain: 'bg-warning-soft',
    inhibit: 'bg-[var(--color-vote-inhibit-soft)]',
    pending: 'bg-canvas-sunk',
};

export const VOTE_DOT: Record<VoteKey | 'pending', string> = {
    yes: 'bg-success',
    no: 'bg-critical',
    abstain: 'bg-[var(--color-warning)]',
    inhibit: 'bg-[var(--color-vote-inhibit)]',
    pending: 'bg-line-strong',
};

export const VOTE_RING: Record<VoteKey | 'pending', string> = {
    yes: 'bg-success',
    no: 'bg-critical',
    abstain: 'bg-[var(--color-warning)]',
    inhibit: 'bg-[var(--color-vote-inhibit)]',
    pending: 'bg-line-strong',
};

export const VOTE_BADGE: Record<VoteKey | 'pending', string> = {
    yes: 'border-[var(--color-success-line)] bg-success-soft text-success',
    no: 'border-[var(--color-critical-line)] bg-critical-soft text-critical',
    abstain: 'border-[var(--color-warning-line)] bg-warning-soft text-warning',
    inhibit: 'border-[var(--color-vote-inhibit-line)] bg-[var(--color-vote-inhibit-soft)] text-[var(--color-vote-inhibit)]',
    pending: 'border-line bg-canvas-sunk text-ink-muted',
};

type VoteBoardProps = {
    tallies: Tallies;
    /** Larger figures for the rostrum, the chamber display, and tablets. */
    floor?: boolean;
    /** Draws proportional bars beneath the figures. Ignored when `members` is set. */
    chart?: boolean;
    /** Hall-board scale: figures sized to be counted from the public gallery. */
    display?: boolean;
    /** When set, each tally column lists the members who cast that ballot. */
    members?: VoteBoardMember[];
    /**
     * Silent ballot: identical generic avatars, no names, including awaiting.
     * Chip counts come from tallies (and `awaitingCount`), not from the roster order.
     */
    anonymous?: boolean;
    /** Seated members who have not voted. Used when `anonymous` so names are not required. */
    awaitingCount?: number;
    /** Caption for seated members who have not voted. Defaults to awaiting a live ballot. */
    awaitingLabel?: string;
    className?: string;
};

function byName(a: VoteBoardMember, b: VoteBoardMember): number {
    return (a.display_name ?? '').localeCompare(b.display_name ?? '', undefined, { sensitivity: 'base' });
}

function isSeated(member: VoteBoardMember): boolean {
    return member.status === 'present' || member.status === 'late';
}

export function VoteBoard({
    tallies,
    floor = false,
    chart = false,
    display = false,
    members,
    anonymous = false,
    awaitingCount,
    awaitingLabel,
    className,
}: VoteBoardProps) {
    const { t } = useTranslations();
    const roster = members ?? [];
    const showRoster = anonymous || members !== undefined;

    const columns = [
        { key: 'yes' as const, label: t('sessions.vote_yes'), value: tallies.yes },
        { key: 'no' as const, label: t('sessions.vote_no'), value: tallies.no },
        { key: 'abstain' as const, label: t('sessions.vote_abstain'), value: tallies.abstain },
        { key: 'inhibit' as const, label: t('sessions.vote_inhibit'), value: tallies.inhibit },
    ];

    const grouped = Object.fromEntries(
        columns.map((column) => [
            column.key,
            roster.filter((member) => member.has_voted && member.choice === column.key).sort(byName),
        ]),
    ) as Record<VoteKey, VoteBoardMember[]>;

    const awaiting = roster.filter((member) => isSeated(member) && !member.has_voted).sort(byName);
    const pendingCount = anonymous ? (awaitingCount ?? awaiting.length) : awaiting.length;
    const base = Math.max(tallies.total, 1);

    return (
        <div className={cn('flex min-h-0 flex-col', className)}>
            <div
                className={cn(
                    'grid',
                    showRoster
                        ? 'min-h-0 flex-1 grid-cols-1 gap-px overflow-hidden rounded-md bg-line sm:grid-cols-2 xl:grid-cols-4'
                        : 'grid-cols-4 divide-x divide-line px-1',
                )}
                role="group"
                aria-label={t('sessions.tally_label')}
            >
                {columns.map((column) => {
                    const columnMembers = grouped[column.key];

                    return (
                        <div
                            key={column.key}
                            className={cn(
                                'flex min-h-0 flex-col',
                                showRoster
                                    ? cn(
                                          'gap-4 border-t-2 bg-surface px-4 py-4 md:px-5',
                                          column.key === 'yes' && 'border-success',
                                          column.key === 'no' && 'border-critical',
                                          column.key === 'abstain' && 'border-warning',
                                          column.key === 'inhibit' && 'border-vote-inhibit',
                                      )
                                    : 'items-start gap-1.5 px-4 first:pl-0 last:pr-0',
                            )}
                        >
                            <div className="shrink-0 space-y-1">
                                <span
                                    className={cn(
                                        'block font-mono font-semibold tracking-[-0.03em] tabular-nums',
                                        VOTE_LABEL[column.key],
                                        display
                                            ? showRoster
                                                ? 'text-[clamp(1.75rem,3.2vw,3.25rem)] leading-none'
                                                : 'text-[clamp(2.75rem,5.5vw,6.5rem)] leading-none'
                                            : floor
                                              ? 'text-figure-lg'
                                              : 'text-figure',
                                    )}
                                >
                                    {column.value}
                                </span>
                                <span
                                    className={cn(
                                        'block font-bold tracking-[0.08em] uppercase',
                                        VOTE_LABEL[column.key],
                                        display ? 'text-[clamp(0.75rem,1.05vw,1.25rem)]' : 'text-2xs',
                                    )}
                                >
                                    {column.label}
                                </span>
                            </div>

                            {showRoster ? (
                                <ul
                                    className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto"
                                    aria-label={column.label}
                                >
                                    {anonymous ? (
                                        column.value === 0 ? (
                                            <li
                                                className={cn(
                                                    'font-medium text-ink-faint',
                                                    display ? 'text-[clamp(0.8125rem,1vw,1.125rem)]' : 'text-sm',
                                                )}
                                            >
                                                {t('sessions.vote_none')}
                                            </li>
                                        ) : (
                                            Array.from({ length: column.value }, (_, index) => (
                                                <li key={`${column.key}-${index}`}>
                                                    <AnonymousVoteChip tone={column.key} display={display} />
                                                </li>
                                            ))
                                        )
                                    ) : columnMembers.length === 0 ? (
                                        <li
                                            className={cn(
                                                'font-medium text-ink-faint',
                                                display ? 'text-[clamp(0.8125rem,1vw,1.125rem)]' : 'text-sm',
                                            )}
                                        >
                                            {t('sessions.vote_none')}
                                        </li>
                                    ) : (
                                        columnMembers.map((member) => (
                                            <li key={member.id}>
                                                <VoteMemberChip member={member} tone={column.key} display={display} />
                                            </li>
                                        ))
                                    )}
                                </ul>
                            ) : null}
                        </div>
                    );
                })}
            </div>

            {showRoster && pendingCount > 0 ? (
                <div className={cn('shrink-0 border-t border-line', display ? 'mt-6 pt-5' : 'mt-4 pt-4')}>
                    <p className={cn('label-eyebrow', display && 'text-[clamp(0.75rem,1vw,1.125rem)]')}>
                        {awaitingLabel ?? t('sessions.vote_awaiting')}
                    </p>
                    <ul className={cn('grid', display ? 'mt-4 grid-cols-2 gap-x-4 gap-y-3 xl:grid-cols-4' : 'mt-3 grid-cols-2 gap-3')}>
                        {anonymous
                            ? Array.from({ length: pendingCount }, (_, index) => (
                                  <li key={`awaiting-${index}`}>
                                      <AnonymousVoteChip tone="pending" display={display} />
                                  </li>
                              ))
                            : awaiting.map((member) => (
                                  <li key={member.id}>
                                      <VoteMemberChip member={member} tone="pending" display={display} />
                                  </li>
                              ))}
                    </ul>
                </div>
            ) : null}

            {chart && !showRoster ? (
                <div className="mt-5 space-y-3 border-t border-line pt-5" aria-hidden="true">
                    {columns.map((column) => {
                        const pct = Math.round((column.value / base) * 100);

                        return (
                            <div
                                key={column.key}
                                className={cn(
                                    'grid items-center gap-3',
                                    display
                                        ? 'grid-cols-[clamp(4.5rem,7vw,9rem)_minmax(0,1fr)_clamp(2.75rem,4vw,5rem)]'
                                        : 'grid-cols-[4.5rem_minmax(0,1fr)_2.75rem]',
                                )}
                            >
                                <span
                                    className={cn(
                                        'text-ink-muted',
                                        display ? 'text-[clamp(0.875rem,1.1vw,1.375rem)]' : 'text-sm',
                                    )}
                                >
                                    {column.label}
                                </span>
                                <div
                                    className={cn(
                                        'min-w-0 overflow-hidden rounded-full bg-chart-track',
                                        display ? 'h-[clamp(0.625rem,1.1vw,1.25rem)]' : 'h-2.5',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'h-full rounded-full transition-[width] duration-500',
                                            VOTE_BAR[column.key],
                                        )}
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                                <span
                                    className={cn(
                                        'text-right font-mono font-semibold text-ink tabular-nums',
                                        display ? 'text-[clamp(0.875rem,1.1vw,1.375rem)]' : 'text-sm',
                                    )}
                                >
                                    {pct}%
                                </span>
                            </div>
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}

function AnonymousVoteChip({ tone, display }: { tone: VoteKey | 'pending'; display: boolean }) {
    return (
        <div className="flex min-w-0 items-center gap-3" aria-hidden="true">
            <div className={cn('shrink-0 rounded-full p-0.5', tone === 'pending' ? 'bg-line-strong' : VOTE_RING[tone])}>
                <span
                    className={cn(
                        'flex items-center justify-center rounded-full border-2 border-surface',
                        VOTE_SOFT[tone],
                        tone === 'pending' ? 'text-ink-muted' : VOTE_LABEL[tone],
                        display ? 'size-[clamp(2.25rem,2.6vw,3rem)]' : 'size-9',
                    )}
                >
                    <User
                        aria-hidden="true"
                        strokeWidth={1.75}
                        className={display ? 'size-[clamp(1rem,1.2vw,1.25rem)]' : 'size-4'}
                    />
                </span>
            </div>
        </div>
    );
}

function VoteMemberChip({
    member,
    tone,
    display,
}: {
    member: VoteBoardMember;
    tone: VoteKey | 'pending';
    display: boolean;
}) {
    const name = withHonorific(member.display_name) ?? member.display_name ?? '';

    return (
        <div className="flex min-w-0 items-center gap-3">
            <div className={cn('shrink-0 rounded-full p-0.5', tone === 'pending' ? 'bg-line-strong' : VOTE_RING[tone])}>
                <UserAvatar
                    name={member.display_name}
                    src={member.avatar_url}
                    alt={name}
                    className={cn('border-2 border-surface', display ? 'size-[clamp(2.25rem,2.6vw,3rem)]' : 'size-9')}
                    fallbackClassName={cn(
                        VOTE_SOFT[tone],
                        tone === 'pending' ? 'text-ink-muted' : VOTE_LABEL[tone],
                        display && 'text-[clamp(0.625rem,0.8vw,0.875rem)]',
                    )}
                />
            </div>
            <span
                className={cn(
                    'min-w-0 truncate font-semibold tracking-[-0.015em] text-ink',
                    display ? 'text-[clamp(0.8125rem,1.05vw,1.25rem)]' : 'text-sm',
                    tone === 'pending' && 'text-ink-muted',
                )}
            >
                {name}
            </span>
        </div>
    );
}

/** The total ballots recorded, as a footnote under the board. */
export function VoteTotal({ tallies, expected, display = false }: { tallies: Tallies; expected?: number; display?: boolean }) {
    const { t } = useTranslations();
    const className = cn('label-eyebrow', display && 'text-[clamp(0.75rem,1vw,1.125rem)]');

    if (expected !== undefined && expected > 0) {
        return <p className={className}>{t('sessions.ballots_recorded_of', { count: tallies.total, of: expected })}</p>;
    }

    return <p className={className}>{t('sessions.ballots_recorded', { count: tallies.total })}</p>;
}
