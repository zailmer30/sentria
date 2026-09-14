import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * The index-page masthead: a section eyebrow, a heavy title, a one-line
 * purpose, and the page's actions on the same row. Detail pages keep using
 * `PageHeader`; this shape is for registers.
 */

type IndexHeaderProps = {
    eyebrow: string;
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
};

export function IndexHeader({ eyebrow, title, description, actions, className }: IndexHeaderProps) {
    return (
        <header className={cn('flex flex-wrap items-start justify-between gap-x-6 gap-y-3', className)}>
            <div className="min-w-0">
                <p className="text-eyebrow text-accent">{eyebrow}</p>
                <h1 className="mt-1 text-2xl font-bold tracking-tight text-floor-plate dark:text-accent">{title}</h1>
                {description ? <p className="mt-1.5 text-sm text-ink-muted">{description}</p> : null}
            </div>
            {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </header>
    );
}
