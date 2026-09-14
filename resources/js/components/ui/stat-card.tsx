import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * One counted fact, stated large enough to read at a glance from across a desk.
 *
 * The figure is the point of the card, so it comes before any commentary and
 * nothing decorative is allowed above it. The optional icon is a landmark for
 * scanning a grid of eight of these, not an illustration — it stays faint.
 */

type StatCardProps = {
    label: string;
    /** Pre-formatted by the caller: counts, durations, "12 of 15". */
    value: ReactNode;
    /** A `MetricDelta`, a caption, or nothing. */
    detail?: ReactNode;
    icon?: LucideIcon;
    /** A range toggle or a link, top right. */
    action?: ReactNode;
    /** Progress bar, sparkline, or a row of chips beneath the figure. */
    children?: ReactNode;
    /** Marks the card whose subject is currently live. */
    live?: boolean;
    className?: string;
};

export function StatCard({
    label,
    value,
    detail,
    icon: Icon,
    action,
    children,
    live = false,
    className,
}: StatCardProps) {
    return (
        <Card
            className={cn(
                'relative gap-3 overflow-hidden p-5',
                live && 'border-[var(--color-live-line)]',
                className,
            )}
        >
            {live ? (
                <span
                    aria-hidden="true"
                    className="animate-advance-rule absolute inset-x-0 top-0 h-0.5 bg-live"
                />
            ) : null}

            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2">
                    {Icon ? (
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-canvas-sunk text-ink-faint">
                            <Icon aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                        </span>
                    ) : null}
                    <p className="label-eyebrow truncate">{label}</p>
                </div>
                {action ? <div className="-mt-1 -mr-1 shrink-0">{action}</div> : null}
            </div>

            <p className="font-mono text-figure font-medium tracking-[-0.03em] text-ink">{value}</p>

            {detail}
            {children}
        </Card>
    );
}

/**
 * The dashboard grid. Twelve columns on desktop so a card can claim three
 * (a figure), six (a list) or the full width (a chart) without a bespoke
 * layout each time.
 */
export function WidgetGrid({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div className={cn('grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12', className)}>
            {children}
        </div>
    );
}
