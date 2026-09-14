import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * An empty case is still a case. These states say what belongs here and how it
 * gets here, rather than reporting that nothing was found.
 */

type EmptyStateProps = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
    /** Inside a register cell the state is already framed, so drop the frame. */
    bare?: boolean;
};

export function EmptyState({ icon: Icon, title, description, action, className, bare = false }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center gap-2.5 px-6 py-14 text-center',
                !bare && 'rounded-[var(--radius-lg)] border border-line bg-surface-alt',
                className,
            )}
        >
            {Icon ? (
                <span className="mb-0.5 flex size-10 items-center justify-center rounded-[var(--radius-md)] border border-line bg-surface text-ink-faint shadow-[var(--shadow-xs)]">
                    <Icon aria-hidden="true" className="size-4.5" strokeWidth={1.75} />
                </span>
            ) : null}
            <p className="text-sm font-semibold text-ink">{title}</p>
            {description ? <p className="max-w-md text-sm text-ink-subtle">{description}</p> : null}
            {action ? <div className="mt-1.5">{action}</div> : null}
        </div>
    );
}
