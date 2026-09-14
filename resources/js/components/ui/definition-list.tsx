import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * A record's metadata, read as a ruled two-column list. The label column is
 * fixed so the values align down the page — a detail surface is scanned for one
 * field, not read top to bottom.
 */

export function DefinitionList({ children, className }: { children: ReactNode; className?: string }) {
    return <dl className={cn('divide-y divide-line border-y border-line', className)}>{children}</dl>;
}

type DefinitionProps = {
    label: string;
    children: ReactNode;
    className?: string;
    /** Mono tabular figures for dates, references and counts. */
    numeric?: boolean;
};

export function Definition({ label, children, className, numeric = false }: DefinitionProps) {
    return (
        <div className={cn('grid gap-1 py-2.5 sm:grid-cols-[minmax(9rem,13rem)_1fr] sm:gap-4', className)}>
            <dt className="text-xs text-ink-subtle">{label}</dt>
            <dd className={cn('text-sm text-ink', numeric && 'font-mono text-xs')}>{children}</dd>
        </div>
    );
}
