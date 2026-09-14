import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

/**
 * Laravel's paginator links. The current page is the only one that takes the
 * accent, because on this strip "where I am" is the single fact worth colour.
 */

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationProps = {
    links: PaginationLink[];
    /** Accessible name for the nav landmark. */
    label: string;
    className?: string;
};

export function Pagination({ links, label, className }: PaginationProps) {
    // A single-page result set still ships prev/next; nothing to navigate.
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label={label} className={cn('flex flex-wrap items-center gap-1', className)}>
            {links.map((link, index) => {
                const key = `${link.label}-${index}`;
                const classes = cn(
                    'inline-flex h-8 min-w-8 items-center justify-center rounded-[var(--radius-md)] border px-2',
                    'font-mono text-xs transition-colors duration-[var(--duration-fast)]',
                    link.active
                        ? 'border-accent bg-accent text-[var(--color-accent-on)]'
                        : 'border-line bg-surface text-ink-muted hover:border-line-strong hover:text-ink',
                );

                if (!link.url) {
                    return (
                        <span
                            key={key}
                            aria-hidden="true"
                            className={cn(classes, 'cursor-default border-line bg-canvas-sunk text-ink-faint')}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                }

                return (
                    <Link
                        key={key}
                        href={link.url}
                        preserveScroll
                        aria-current={link.active ? 'page' : undefined}
                        className={classes}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                );
            })}
        </nav>
    );
}
