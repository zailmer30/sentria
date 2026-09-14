import { VOTE_BADGE, VOTE_DOT, VOTE_RING, type VoteKey } from '@/components/session/VoteBoard';
import { Panel, PanelBody } from '@/components/ui/panel';
import { UserAvatar } from '@/components/users/UserAvatar';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { useMemo, useState } from 'react';

export type VotingMember = {
    id: string;
    display_name: string | null;
    avatar_url: string | null;
    position_title?: string | null;
    district?: string | null;
    status: string;
    has_voted: boolean;
    choice?: string | null;
    cast_at?: string | null;
    is_presiding?: boolean;
};

type FilterKey = 'all' | VoteKey | 'pending';

function formatCastTime(iso: string | null | undefined): string | null {
    if (!iso) {
        return null;
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: false });
}

function memberRole(member: VotingMember, t: (key: string) => string): string {
    if (member.is_presiding) {
        return t('sessions.roll_call.presiding');
    }

    const parts = [member.position_title, member.district].filter(Boolean);

    if (parts.length > 0) {
        return parts.join(' · ');
    }

    return t('sessions.roll_call.member');
}

function choiceKey(choice: string | null | undefined): VoteKey | 'pending' {
    if (choice === 'yes' || choice === 'no' || choice === 'abstain' || choice === 'inhibit') {
        return choice;
    }

    return 'pending';
}

export function VotingMemberBoard({
    members,
    presentCount,
    absentCount,
    display = false,
    className,
}: {
    members: VotingMember[];
    presentCount?: number;
    absentCount?: number;
    /**
     * Hall-board scale. Filters are withdrawn — nobody in the gallery can press
     * them, and a board that shows a filtered roll call is a board that lies —
     * and the roll runs in columns so a full chamber fits without scrolling.
     */
    display?: boolean;
    className?: string;
}) {
    const { t } = useTranslations();
    const [filter, setFilter] = useState<FilterKey>('all');

    const filters = useMemo(
        () =>
            [
                { key: 'all' as const, label: t('sessions.roll_call.filter_all') },
                { key: 'yes' as const, label: t('sessions.vote_yes') },
                { key: 'no' as const, label: t('sessions.vote_no') },
                { key: 'abstain' as const, label: t('sessions.vote_abstain') },
                { key: 'inhibit' as const, label: t('sessions.vote_inhibit') },
                { key: 'pending' as const, label: t('sessions.vote_pending') },
            ] as const,
        [t],
    );

    const filtered = members.filter((member) => {
        if (display || filter === 'all') {
            return true;
        }
        if (filter === 'pending') {
            return !member.has_voted;
        }
        return member.choice === filter;
    });

    const present = presentCount ?? members.filter((m) => m.status === 'present' || m.status === 'late').length;
    const absent = absentCount ?? members.filter((m) => m.status === 'absent').length;

    return (
        <Panel as="section" className={cn('overflow-hidden', className)}>
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div className="min-w-0">
                    <h2
                        className={cn(
                            'font-semibold tracking-[-0.01em] text-ink',
                            display ? 'text-[clamp(1rem,1.4vw,1.625rem)]' : 'text-base',
                        )}
                    >
                        {t('sessions.roll_call.title')}
                    </h2>
                    <p className={cn('mt-0.5 text-ink-muted', display ? 'text-[clamp(0.875rem,1.05vw,1.25rem)]' : 'text-sm')}>
                        {t('sessions.roll_call.summary', { present, absent })}
                    </p>
                </div>

                {!display && members.length > 0 ? (
                    <div
                        className="flex flex-wrap items-center gap-1.5"
                        role="toolbar"
                        aria-label={t('sessions.roll_call.filters')}
                    >
                        {filters.map((item) => {
                            const active = filter === item.key;
                            const tone = item.key === 'all' ? 'bg-ink' : VOTE_DOT[item.key];

                            return (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => setFilter(item.key)}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                                        item.key === 'all'
                                            ? active
                                                ? 'bg-surface text-ink shadow-[var(--shadow-xs)] ring-1 ring-line'
                                                : 'bg-transparent text-ink-muted hover:bg-surface/70 hover:text-ink'
                                            : active
                                              ? 'bg-canvas-sunk text-ink'
                                              : 'text-ink-muted hover:bg-canvas-sunk hover:text-ink',
                                    )}
                                >
                                    {item.key !== 'all' ? (
                                        <span aria-hidden="true" className={cn('size-1.5 rounded-full', tone)} />
                                    ) : null}
                                    {item.label}
                                </button>
                            );
                        })}
                    </div>
                ) : null}
            </div>

            <PanelBody className="px-0 py-0">
                {members.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-ink-muted">{t('sessions.roll_call.empty')}</p>
                ) : (
                    <>
                        <ul className={display ? '-mb-px grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3' : undefined}>
                            {filtered.map((member) => {
                                const time = formatCastTime(member.cast_at);
                                const key = choiceKey(member.choice);
                                const isPresent = member.status === 'present' || member.status === 'late';

                                return (
                                    <li
                                        key={member.id}
                                        className={cn(
                                            'flex items-center gap-3 border-b border-line px-5 py-3',
                                            display ? 'gap-4 py-3.5' : 'last:border-b-0',
                                        )}
                                    >
                                        <div className="relative shrink-0">
                                            <div className={cn('rounded-full p-[2px]', VOTE_RING[key])}>
                                                <UserAvatar
                                                    name={member.display_name}
                                                    src={member.avatar_url}
                                                    alt={member.display_name ?? ''}
                                                    className={cn(
                                                        'border-2 border-surface',
                                                        display ? 'size-[clamp(2.5rem,3vw,3.5rem)]' : 'size-10',
                                                    )}
                                                />
                                            </div>
                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'absolute right-0 bottom-0 rounded-full border-2 border-surface',
                                                    display ? 'size-3' : 'size-2.5',
                                                    isPresent ? 'bg-success' : 'bg-line-strong',
                                                )}
                                            />
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p
                                                className={cn(
                                                    'truncate font-semibold text-ink',
                                                    display ? 'text-[clamp(0.9375rem,1.15vw,1.375rem)]' : 'text-sm',
                                                )}
                                            >
                                                {member.display_name}
                                            </p>
                                            <p
                                                className={cn(
                                                    'truncate text-ink-muted',
                                                    display ? 'text-[clamp(0.75rem,0.9vw,1.0625rem)]' : 'text-xs',
                                                )}
                                            >
                                                {memberRole(member, t)}
                                            </p>
                                        </div>

                                        {display ? null : (
                                            <span className="w-12 shrink-0 text-right font-mono text-xs text-ink-faint tabular-nums">
                                                {time ?? ''}
                                            </span>
                                        )}

                                        <span
                                            className={cn(
                                                'inline-flex items-center justify-center gap-1.5 rounded-full border font-semibold',
                                                display
                                                    ? 'min-w-[clamp(5.5rem,7.5vw,9rem)] px-3 py-1.5 text-[clamp(0.75rem,0.95vw,1.125rem)]'
                                                    : 'min-w-[5.5rem] px-2.5 py-1 text-xs',
                                                VOTE_BADGE[key],
                                            )}
                                        >
                                            <span aria-hidden="true" className={cn('size-1.5 rounded-full', VOTE_DOT[key])} />
                                            {key === 'pending'
                                                ? t('sessions.roll_call.no_ballot')
                                                : t(`sessions.vote_${key}` as 'sessions.vote_yes')}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>

                        {filtered.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-ink-muted">{t('sessions.roll_call.empty_filter')}</p>
                        ) : null}
                    </>
                )}
            </PanelBody>
        </Panel>
    );
}
