import { StatusChip, type StatusTone } from '@/components/ui/status';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ReadingPackItem } from '@/pages/Sessions/Floor/shared';

function statusTone(status: string): StatusTone {
    if (status === 'in-progress') {
        return 'moving';
    }

    if (status === 'completed') {
        return 'final';
    }

    return 'draft';
}

function statusLabel(status: string, t: (key: string) => string): string {
    if (status === 'in-progress') {
        return t('sessions.reading.status_current');
    }

    if (status === 'completed') {
        return t('sessions.reading.status_completed');
    }

    return t('sessions.reading.status_pending');
}

type MemberAgendaRailProps = {
    items: ReadingPackItem[];
    selectedId: string | null;
    currentItemId: string | null;
    followingFloor: boolean;
    onSelect: (itemId: string) => void;
    onFollowFloor: () => void;
    className?: string;
};

export function MemberAgendaRail({
    items,
    selectedId,
    currentItemId,
    followingFloor,
    onSelect,
    onFollowFloor,
    className,
}: MemberAgendaRailProps) {
    const { t } = useTranslations();

    return (
        <aside
            className={cn(
                'flex h-full min-h-0 flex-col overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface shadow-[var(--shadow-xs)]',
                className,
            )}
        >
            <div className="flex shrink-0 items-center justify-between gap-2 border-b border-line px-4 py-3">
                <h2 className="text-sm font-semibold text-ink">{t('sessions.agenda')}</h2>
                {!followingFloor ? (
                    <button
                        type="button"
                        onClick={onFollowFloor}
                        className="rounded-[var(--radius-sm)] px-2 py-1 text-xs font-medium text-accent hover:bg-accent-soft"
                    >
                        {t('sessions.reading.follow_floor')}
                    </button>
                ) : (
                    <span className="text-2xs font-medium tracking-wide text-ink-subtle uppercase">
                        {t('sessions.reading.following')}
                    </span>
                )}
            </div>

            <ul className="min-h-0 flex-1 overflow-y-auto p-2" role="listbox" aria-label={t('sessions.agenda')}>
                {items.length === 0 ? (
                    <li className="px-3 py-6 text-center text-sm text-ink-muted">{t('sessions.agenda_empty')}</li>
                ) : (
                    items.map((item) => {
                        const selected = item.id === selectedId;
                        const onFloor = item.id === currentItemId;
                        const number = item.item_number ?? String(item.position);
                        const depth = nestingDepth(item, items);

                        return (
                            <li key={item.id} style={depth > 0 ? { paddingLeft: `${depth * 0.75}rem` } : undefined}>
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected={selected}
                                    onClick={() => onSelect(item.id)}
                                    className={cn(
                                        'flex w-full items-start gap-2.5 rounded-[var(--radius-md)] border px-2.5 py-2.5 text-left transition-colors',
                                        selected || onFloor
                                            ? 'border-accent-line bg-accent-soft shadow-[var(--shadow-xs)]'
                                            : 'border-transparent hover:bg-canvas-sunk',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-2xs font-semibold tabular-nums',
                                            onFloor || selected
                                                ? 'bg-accent text-[var(--color-accent-on)]'
                                                : 'bg-canvas-sunk text-ink-muted',
                                        )}
                                    >
                                        {number}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium leading-snug text-ink">
                                            {item.title}
                                        </span>
                                        <span className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <StatusChip tone={statusTone(item.status)} size="sm">
                                                {statusLabel(item.status, t)}
                                            </StatusChip>
                                            {item.voting_open ? (
                                                <StatusChip tone="live" size="sm">
                                                    {t('sessions.reading.status_voting')}
                                                </StatusChip>
                                            ) : null}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        );
                    })
                )}
            </ul>
        </aside>
    );
}

function nestingDepth(item: ReadingPackItem, items: ReadingPackItem[]): number {
    const byId = new Map(items.map((row) => [row.id, row]));
    let depth = 0;
    let parentId = item.parent_id ?? null;

    while (parentId) {
        depth += 1;
        parentId = byId.get(parentId)?.parent_id ?? null;

        if (depth > 4) {
            break;
        }
    }

    return depth;
}
