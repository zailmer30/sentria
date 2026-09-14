import { cn } from '@/lib/utils';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';

/**
 * Change against the previous period. Direction is carried by an arrow as well
 * as a colour, because "up" and "down" are the whole message and a colourblind
 * reader gets no second chance at it.
 *
 * Up is not good and down is not bad in this domain — a rise in overdue
 * referrals is a problem — so the caller states the reading with `intent`
 * rather than the component assuming that more is better.
 */

type MetricDeltaProps = {
    /** Percentage change. Sign carries the direction; the caller pre-rounds. */
    value: number;
    /** How to read the direction. `neutral` keeps it in ink. */
    intent?: 'more-is-better' | 'less-is-better' | 'neutral';
    /** Names the comparison period, e.g. "from last month". */
    caption?: string;
    className?: string;
};

export function MetricDelta({
    value,
    intent = 'neutral',
    caption,
    className,
}: MetricDeltaProps) {
    const flat = Math.round(value) === 0;
    const rising = value > 0;
    const Icon = flat ? Minus : rising ? ArrowUpRight : ArrowDownRight;

    const good = intent === 'more-is-better' ? rising : intent === 'less-is-better' ? !rising : null;
    const tone =
        flat || good === null ? 'text-ink-muted' : good ? 'text-success' : 'text-[var(--color-live-ink)]';

    return (
        <p className={cn('flex flex-wrap items-baseline gap-x-1.5 text-xs', tone, className)}>
            <span className="inline-flex items-baseline gap-0.5 font-medium">
                <Icon aria-hidden="true" strokeWidth={2.25} className="size-3.5 self-center" />
                <span className="font-mono">
                    {flat ? '0' : `${rising ? '+' : ''}${value}`}%
                </span>
            </span>
            {caption ? <span className="text-ink-faint">{caption}</span> : null}
        </p>
    );
}
