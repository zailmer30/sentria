import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * "What can I do to this record" belongs in one place. Grouping the
 * permission-gated actions into a single bar keeps them from scattering down
 * the page, and makes it obvious when the answer is "nothing right now".
 */

type ToolbarProps = {
    children: ReactNode;
    /** Accessible name, e.g. "Session workflow". */
    label: string;
    className?: string;
};

export function Toolbar({ children, label, className }: ToolbarProps) {
    return (
        <div
            role="group"
            aria-label={label}
            className={cn(
                'flex flex-wrap items-center gap-2 rounded-[var(--radius-lg)] border border-line bg-surface px-4 py-3 shadow-[var(--shadow-xs)]',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** A labelled divider inside a toolbar, separating stages of a workflow. */
export function ToolbarGroup({ label, children }: { label?: string; children: ReactNode }) {
    return (
        <div className="flex flex-wrap items-center gap-2 border-line not-first:border-l not-first:pl-3">
            {label ? <span className="label-eyebrow">{label}</span> : null}
            {children}
        </div>
    );
}
