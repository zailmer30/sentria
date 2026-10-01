import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { formatAgendaNumber, type ReadingPackItem } from '@/pages/Sessions/Floor/shared';
import { Check } from 'lucide-react';
import { useEffect, useRef } from 'react';

type OrderOfBusinessProps = {
    items: ReadingPackItem[];
    currentItemId: string | null;
    className?: string;
    /**
     * Hall-board scale. The list also becomes self-scrolling: a projected
     * agenda has no operator to drag it, so the item on the floor has to walk
     * itself back into view as the sitting advances.
     */
    display?: boolean;
    /**
     * Secretariat rail: cap height and scroll inside the panel so a long
     * reading pack does not stretch the whole page.
     */
    scrollable?: boolean;
};

export function OrderOfBusiness({
    items,
    currentItemId,
    className,
    display = false,
    scrollable = false,
}: OrderOfBusinessProps) {
    const { t } = useTranslations();
    const currentRef = useRef<HTMLLIElement | null>(null);

    const completed = items.filter((item) => item.status === 'completed' || item.status === 'postponed' || item.status === 'considered').length;
    const progress = items.length > 0 ? Math.round((completed / items.length) * 100) : 0;

    const trackCurrent = display || scrollable;

    useEffect(() => {
        if (!trackCurrent) {
            return;
        }

        currentRef.current?.scrollIntoView({
            block: scrollable ? 'nearest' : 'center',
            behavior: 'smooth',
        });
    }, [trackCurrent, scrollable, currentItemId]);

    return (
        <Panel
            as="section"
            className={cn(
                (display || scrollable) && 'flex min-h-0 flex-col overflow-hidden',
                scrollable && 'max-h-[min(calc(100dvh-8rem),42rem)]',
                className,
            )}
        >
            <PanelHead className={cn('px-4 py-3', (display || scrollable) && 'shrink-0')}>
                <PanelTitle className={display ? 'text-[clamp(0.875rem,1vw,1.125rem)]' : undefined}>
                    {t('sessions.order_of_business')}
                </PanelTitle>
                <span
                    className={cn(
                        'font-mono text-ink-muted tabular-nums',
                        display ? 'text-[clamp(0.8125rem,0.95vw,1.0625rem)]' : 'text-xs',
                    )}
                >
                    {completed}/{items.length}
                </span>
            </PanelHead>
            <PanelBody
                className={cn(
                    'space-y-3 px-4 py-3',
                    (display || scrollable) && 'min-h-0 flex-1 overflow-y-auto overscroll-contain',
                )}
            >
                <div
                    className={cn('overflow-hidden rounded-full bg-[var(--color-chart-track)]', display ? 'h-1.5' : 'h-1')}
                    role="progressbar"
                    aria-valuenow={progress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={t('sessions.order_of_business')}
                >
                    <div
                        className="h-full rounded-full bg-ink/80 transition-[width] duration-500"
                        style={{ width: `${progress}%` }}
                    />
                </div>

                {items.length === 0 ? (
                    <p className="text-sm text-ink-muted">{t('sessions.agenda_empty')}</p>
                ) : (
                    <ol className="space-y-0.5">
                        {items.map((item, index) => {
                            const done = item.status === 'completed';
                            const postponed = item.status === 'postponed';
                            const considered = item.status === 'considered';
                            const current = item.id === currentItemId || item.status === 'in-progress';
                            const number = formatAgendaNumber(item.item_number, index);
                            const depth = nestingDepth(item, items);

                            return (
                                <li
                                    key={item.id}
                                    ref={current ? currentRef : undefined}
                                    className={cn(
                                        'flex items-start gap-2.5 rounded-[var(--radius-md)] px-2 py-1.5',
                                        display && 'gap-3 py-2',
                                        current && 'bg-accent-soft',
                                    )}
                                    style={depth > 0 ? { paddingLeft: `${0.5 + depth * 0.75}rem` } : undefined}
                                >
                                    <span
                                        className={cn(
                                            'mt-0.5 flex shrink-0 items-center justify-center rounded-full border',
                                            display ? 'size-[1.375rem]' : 'size-5',
                                            done
                                                ? 'border-success bg-success text-ink-inverse'
                                                : postponed
                                                  ? 'border-[var(--color-warning-line)] bg-warning-soft text-warning'
                                                  : considered
                                                    ? 'border-line-strong bg-canvas-sunk text-ink-muted'
                                                : current
                                                  ? 'border-accent bg-accent text-ink-inverse'
                                                  : 'border-line-strong bg-surface text-transparent',
                                        )}
                                        aria-hidden="true"
                                    >
                                        {done || current || considered ? <Check className="size-3" strokeWidth={2.5} /> : null}
                                    </span>
                                    <span
                                        className={cn(
                                            'shrink-0 font-mono tabular-nums',
                                            display ? 'text-[clamp(0.8125rem,0.95vw,1.0625rem)]' : 'text-xs',
                                            current ? 'font-semibold text-accent-ink' : 'text-ink-faint',
                                        )}
                                    >
                                        {number}
                                    </span>
                                    <span
                                        className={cn(
                                            'min-w-0 flex-1 leading-snug',
                                            display ? 'text-[clamp(0.875rem,1.05vw,1.1875rem)]' : 'text-sm',
                                            done
                                                ? 'text-ink-muted line-through decoration-line-strong'
                                                : postponed
                                                  ? 'text-ink-muted'
                                                  : considered
                                                    ? 'text-ink-muted'
                                                  : 'font-medium text-ink',
                                            current && !done && 'text-accent-ink',
                                        )}
                                    >
                                        {item.title}
                                        {considered && !current ? (
                                            <span className="mt-0.5 block text-2xs font-medium tracking-wide text-ink-faint uppercase">
                                                {t('sessions.vote_pending')}
                                            </span>
                                        ) : null}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                )}
            </PanelBody>
        </Panel>
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
