import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * The panel is the container of this interface: one record, one queue, one
 * group of facts. It is the shadcn card plus the two states a legislative
 * record needs — `raised` for the thing currently in hand, `live` for the one
 * session actually running.
 *
 * Panels do not nest. If content inside a panel needs its own frame, it wants
 * a `PanelSection` or a rule, not a second box.
 */

type PanelProps = {
    children: ReactNode;
    className?: string;
    id?: string;
    /** Lifts the panel for the one record that is currently live. */
    raised?: boolean;
    /** Draws the live rule across the top edge. Session in progress only. */
    live?: boolean;
    as?: 'div' | 'section' | 'article' | 'aside' | 'li';
};

export function Panel({
    children,
    className,
    id,
    raised = false,
    live = false,
    as: Tag = 'div',
}: PanelProps) {
    return (
        <Card
            asChild
            className={cn(
                'relative overflow-hidden',
                raised ? 'shadow-[var(--shadow-md)]' : 'shadow-[var(--shadow-xs)]',
                live && 'border-[var(--color-live-line)]',
                className,
            )}
        >
            <Tag id={id}>
                {live ? (
                    <span
                        aria-hidden="true"
                        className="animate-advance-rule absolute inset-x-0 top-0 z-10 h-0.5 bg-live"
                    />
                ) : null}
                {children}
            </Tag>
        </Card>
    );
}

type PanelHeadProps = {
    children: ReactNode;
    className?: string;
    /** Sits the head on the recessed tone, the way a table head reads. */
    sunk?: boolean;
};

export function PanelHead({ children, className, sunk = false }: PanelHeadProps) {
    return (
        <div
            data-slot="panel-head"
            className={cn(
                'flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-line px-5 py-3.5',
                sunk && 'bg-surface-alt',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function PanelTitle({ children, className }: { children: ReactNode; className?: string }) {
    return <h2 className={cn('text-sm font-semibold text-ink', className)}>{children}</h2>;
}

export function PanelBody({
    children,
    className,
    id,
}: {
    children: ReactNode;
    className?: string;
    id?: string;
}) {
    return (
        <div id={id} data-slot="panel-body" className={cn('px-5 py-4', className)}>
            {children}
        </div>
    );
}

export function PanelFoot({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div
            data-slot="panel-foot"
            className={cn(
                'flex flex-wrap items-center gap-2 border-t border-line bg-surface-alt px-5 py-3',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** A ruled division inside a panel, for when a second box would be wrong. */
export function PanelSection({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div
            data-slot="panel-section"
            className={cn('border-t border-line px-5 py-4 first:border-t-0', className)}
        >
            {children}
        </div>
    );
}
