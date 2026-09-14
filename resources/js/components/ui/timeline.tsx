import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Legislative history is a chain, not a feed: each event links to the one
 * before it. The connecting rule is continuous because the record is
 * continuous — that is the claim this system makes about itself, and the audit
 * trail is what backs it.
 */

export function Timeline({ children, className }: { children: ReactNode; className?: string }) {
    return <ol className={cn('relative', className)}>{children}</ol>;
}

type TimelineItemProps = {
    label: string;
    /** Formatted date; kept mono so a column of dates aligns. */
    when?: string | null;
    children?: ReactNode;
    /** The most recent event, or the one currently in progress. */
    current?: boolean;
    /** Optional landmark icon inside the node. */
    icon?: LucideIcon;
    /** Label shown beside the title when this item is current. */
    badge?: ReactNode;
    className?: string;
};

export function TimelineItem({
    label,
    when,
    children,
    current = false,
    icon: Icon,
    badge,
    className,
}: TimelineItemProps) {
    return (
        <li className={cn('group/item relative flex gap-3', className)}>
            <div className="flex w-6 shrink-0 flex-col items-center">
                <span
                    aria-hidden="true"
                    className={cn(
                        'relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border',
                        current
                            ? 'border-accent bg-accent text-accent-on'
                            : 'border-line-strong bg-surface text-ink-faint',
                    )}
                >
                    {Icon ? (
                        <Icon className="size-3" strokeWidth={1.75} />
                    ) : (
                        <span
                            className={cn(
                                'size-1.5 rounded-full border-2',
                                current ? 'border-accent-on bg-accent-on' : 'border-line-strong bg-surface',
                            )}
                        />
                    )}
                </span>
                <span
                    aria-hidden="true"
                    className="mt-1 w-px flex-1 bg-line-strong group-last/item:hidden"
                />
            </div>
            <div className="min-w-0 flex-1 pb-5 group-last/item:pb-0">
                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <p className="flex min-w-0 flex-wrap items-center gap-2 text-sm font-medium text-ink">
                        <span>{label}</span>
                        {current && badge ? (
                            <span className="inline-flex items-center rounded-full bg-accent-soft px-1.5 py-px text-2xs font-medium text-accent">
                                {badge}
                            </span>
                        ) : null}
                    </p>
                    {when ? <p className="shrink-0 font-mono text-2xs text-ink-faint">{when}</p> : null}
                </div>
                {children ? <div className="mt-1 text-sm text-ink-muted">{children}</div> : null}
            </div>
        </li>
    );
}
