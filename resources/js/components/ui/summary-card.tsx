import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * A counted fact over the filtered register. Clicking one scopes the rows to
 * that subset, which is why the pressed card carries the accent rather than
 * a decorative highlight.
 */

type SummaryCardProps = {
    label: string;
    value: number;
    icon: LucideIcon;
    pressed?: boolean;
    live?: boolean;
    onClick?: () => void;
    className?: string;
};

export function SummaryCard({
    label,
    value,
    icon: Icon,
    pressed = false,
    live = false,
    onClick,
    className,
}: SummaryCardProps) {
    const iconClass = pressed ? 'text-accent' : live ? 'text-live' : 'text-ink-faint';

    return (
        <button
            type="button"
            aria-pressed={pressed}
            onClick={onClick}
            className={cn(
                'rounded-[8px] border bg-surface p-5 text-left shadow-[0_1px_2px_rgb(15_27_61/0.06)]',
                'transition-[border-color,box-shadow,background-color] duration-fast',
                pressed ? 'border-accent bg-accent-soft/40' : 'border-line hover:border-accent-line hover:shadow-sm',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="label-eyebrow">{label}</p>
                <Icon aria-hidden="true" strokeWidth={1.75} className={cn('size-5 shrink-0', iconClass)} />
            </div>
            <p className="mt-4 font-mono text-figure font-medium tracking-[-0.03em] text-ink">{value}</p>
        </button>
    );
}

export function SummaryGrid({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4', className)}>{children}</div>;
}
