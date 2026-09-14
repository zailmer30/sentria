import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * Who did what, when. The audit trail is this product's central claim, so
 * provenance is a permanent fixture of a record rather than something you go
 * looking for: a quiet ruled line of label/value pairs, values in the mono so
 * dates, actors and reference numbers align down a column.
 */

type ProvenanceProps = {
    children: ReactNode;
    className?: string;
    /** Drops the top rule when the strip already follows a divider. */
    bare?: boolean;
};

export function Provenance({ children, className, bare = false }: ProvenanceProps) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-baseline gap-x-5 gap-y-1',
                !bare && 'border-t border-line pt-2.5',
                className,
            )}
        >
            {children}
        </div>
    );
}

type ProvenanceFieldProps = {
    label: string;
    children: ReactNode;
    className?: string;
};

export function ProvenanceField({ label, children, className }: ProvenanceFieldProps) {
    return (
        <span className={cn('inline-flex items-baseline gap-1.5', className)}>
            <span className="label-eyebrow">{label}</span>
            <span className="font-mono text-xs text-ink-muted">{children}</span>
        </span>
    );
}

/**
 * A hash, ULID or reference number. Always mono, always selectable, and never
 * wrapped mid-token — an operator reading one of these out loud needs it whole.
 */
export function Identifier({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <code className={cn('rounded-[var(--radius-xs)] bg-canvas-sunk px-1 py-0.5 font-mono text-xs text-ink-muted', className)}>
            {children}
        </code>
    );
}
