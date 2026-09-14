import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * A short list of records inside a card: the referrals running late, the last
 * ten things that happened, the minutes waiting on a signature.
 *
 * It is deliberately not a register. A register is for scanning forty rows
 * against each other; this is for the five that need a decision, so each row
 * gets a landmark, two lines of identity and one right-aligned fact.
 */

export function QuickList({ children, className }: { children: ReactNode; className?: string }) {
    return <ul className={cn('flex flex-col', className)}>{children}</ul>;
}

type QuickListItemProps = {
    icon?: LucideIcon;
    /** Tints the icon tile. Defaults to neutral; `live` is session-only. */
    tone?: 'neutral' | 'accent' | 'success' | 'warning' | 'critical' | 'live';
    title: string;
    /** Second line: who, when, which committee. */
    meta?: ReactNode;
    /** Right-aligned fact: a count, an amount, a status chip. */
    trailing?: ReactNode;
    /** Makes the whole row a link to the record. */
    href?: string;
    className?: string;
};

const TONE: Record<NonNullable<QuickListItemProps['tone']>, string> = {
    neutral: 'bg-canvas-sunk text-ink-muted',
    accent: 'bg-accent-soft text-accent',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    critical: 'bg-critical-soft text-critical',
    live: 'bg-live-soft text-[var(--color-live-ink)]',
};

export function QuickListItem({
    icon: Icon,
    tone = 'neutral',
    title,
    meta,
    trailing,
    href,
    className,
}: QuickListItemProps) {
    const body = (
        <>
            {Icon ? (
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-[var(--radius-md)]',
                        TONE[tone],
                    )}
                >
                    <Icon aria-hidden="true" strokeWidth={1.75} className="size-4" />
                </span>
            ) : null}

            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-ink">{title}</span>
                {meta ? <span className="block truncate text-xs text-ink-subtle">{meta}</span> : null}
            </span>

            {trailing ? <span className="shrink-0 text-right">{trailing}</span> : null}
        </>
    );

    const shared = cn(
        'flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0',
        className,
    );

    return (
        <li>
            {href ? (
                <Link
                    href={href}
                    className={cn(
                        shared,
                        'transition-colors duration-[var(--duration-fast)] hover:bg-canvas-sunk',
                    )}
                >
                    {body}
                </Link>
            ) : (
                <div className={shared}>{body}</div>
            )}
        </li>
    );
}
