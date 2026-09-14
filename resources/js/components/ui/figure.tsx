import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * A figure is a counted fact read across a room: a tally column, the quorum
 * count, elapsed time. Mono and tabular so the digits never shift width as they
 * change — a clock that reflows every second is unreadable at a bench.
 *
 * Figures stay in ink. The national red belongs to the fact that a thing is
 * live, not to the number itself, so a tally or clock never borrows urgency.
 */

type FigureProps = {
    /** The counted value. Pass a string when the value is formatted (e.g. a clock). */
    value: ReactNode;
    label: string;
    size?: 'sm' | 'md' | 'lg';
    muted?: boolean;
    className?: string;
};

const SIZE: Record<NonNullable<FigureProps['size']>, string> = {
    sm: 'text-figure',
    md: 'text-figure-lg',
    lg: 'text-figure-xl',
};

export function Figure({ value, label, size = 'md', muted = false, className }: FigureProps) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <span
                className={cn(
                    'font-mono font-medium tracking-[-0.02em]',
                    SIZE[size],
                    muted ? 'text-ink-faint' : 'text-ink',
                )}
            >
                {value}
            </span>
            <span className="label-eyebrow">{label}</span>
        </div>
    );
}

/**
 * Figures side by side as one block of record. Hairline separated rather than
 * boxed, so a four-column tally never reads as four floating stat cards.
 */
export function FigureRow({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('grid grid-cols-2 divide-x divide-line sm:grid-cols-4', className)}>{children}</div>;
}

export function FigureCell({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('px-4 py-3 first:pl-0', className)}>{children}</div>;
}
