import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

/**
 * The things this user can start right now. Every tile is permission-gated
 * upstream, so an empty row means "nothing to start", which is itself worth
 * seeing — a tile is never rendered disabled as a hint about someone else's
 * permissions.
 *
 * Tiles are secondary surfaces on purpose. The primary action of a page lives
 * on that page's header, and two competing primaries is one too many.
 */

type ActionTileProps = {
    href: string;
    label: string;
    description?: string;
    icon: LucideIcon;
    className?: string;
};

export function ActionTile({ href, label, description, icon: Icon, className }: ActionTileProps) {
    return (
        <Link
            href={href}
            className={cn(
                'group flex items-center gap-3.5 rounded-[var(--radius-xl)] border border-line bg-surface px-4 py-4 text-left shadow-[var(--shadow-xs)]',
                'transition-[border-color,box-shadow] duration-[var(--duration-fast)] hover:border-[var(--color-accent-line)] hover:shadow-[var(--shadow-sm)]',
                className,
            )}
        >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full border border-line text-ink-muted transition-colors duration-[var(--duration-fast)] group-hover:border-[var(--color-accent-line)] group-hover:text-accent">
                <Icon aria-hidden="true" strokeWidth={1.75} className="size-4" />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-medium text-ink">{label}</span>
                {description ? (
                    <span className="mt-0.5 block text-xs text-ink-subtle">{description}</span>
                ) : null}
            </span>
        </Link>
    );
}

export function ActionTileRow({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4', className)}>
            {children}
        </div>
    );
}
