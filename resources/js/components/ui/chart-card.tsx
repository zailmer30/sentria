import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * A chart with its own frame, heading and controls. The heading states what is
 * being counted and the caption states over what window, so the chart is never
 * the only thing carrying the question it answers.
 *
 * `summary` holds the same fact in text, above the drawing. That is what a
 * screen reader gets, what prints, and what is on screen if the chart has not
 * mounted yet.
 */

type ChartCardProps = {
    title: string;
    description?: string;
    /** A range toggle, a legend, a link. */
    action?: ReactNode;
    /** The headline figure this chart breaks down. */
    summary?: ReactNode;
    children: ReactNode;
    className?: string;
};

export function ChartCard({
    title,
    description,
    action,
    summary,
    children,
    className,
}: ChartCardProps) {
    return (
        <Card className={cn('gap-4 p-5', className)}>
            <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold text-ink">{title}</h2>
                    {description ? (
                        <p className="mt-0.5 text-xs text-ink-subtle">{description}</p>
                    ) : null}
                </div>
                {action ? <div className="shrink-0">{action}</div> : null}
            </div>

            {summary}

            {children}
        </Card>
    );
}
