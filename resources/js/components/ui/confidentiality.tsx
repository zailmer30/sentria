import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * Four confidentiality classes decide who may see a document at all, so the
 * class travels with the record everywhere it appears. It reuses the semantic
 * palette rather than introducing a fifth colour family, and colour is never
 * the only carrier: every mark ships an accessible name and the detail surfaces
 * state the class in words.
 */

export type Confidentiality = 'public' | 'internal' | 'restricted' | 'confidential';

const DOT: Record<Confidentiality, string> = {
    public: 'bg-success',
    internal: 'bg-ink-faint',
    restricted: 'bg-warning',
    confidential: 'bg-critical',
};

const TEXT: Record<Confidentiality, string> = {
    public: 'text-success',
    internal: 'text-ink-muted',
    restricted: 'text-warning',
    confidential: 'text-critical',
};

const EDGE: Record<Confidentiality, string> = {
    public: 'border-l-success',
    internal: 'border-l-ink-faint',
    restricted: 'border-l-warning',
    confidential: 'border-l-critical',
};

export function normalizeConfidentiality(value: string | null | undefined): Confidentiality {
    const key = (value ?? '').toLowerCase();

    if (key === 'internal' || key === 'restricted' || key === 'confidential') {
        return key;
    }

    return 'public';
}

type MarkProps = {
    confidentiality: string | null | undefined;
    /** The translated class name, announced to assistive tech. */
    label: string;
    className?: string;
};

/**
 * A 1px coded edge for a panel that carries a single record. Apply to the panel
 * itself; it replaces the left hairline rather than sitting on top of it.
 */
export function confidentialityEdge(confidentiality: string | null | undefined): string {
    return cn('border-l-2', EDGE[normalizeConfidentiality(confidentiality)]);
}

/** The compact mark for a dense register cell. */
export function ConfidentialityDot({ confidentiality, label, className }: MarkProps) {
    const level = normalizeConfidentiality(confidentiality);

    return (
        <span className={cn('flex items-center', className)} title={label}>
            <span aria-hidden="true" className={cn('size-2 rounded-full', DOT[level])} />
            <span className="sr-only">{label}</span>
        </span>
    );
}

/** Confidentiality in words, for detail pages and legends. */
export function ConfidentialityMark({ confidentiality, label, className }: MarkProps) {
    const level = normalizeConfidentiality(confidentiality);

    return (
        <span className={cn('inline-flex items-center gap-1.5', className)}>
            <span aria-hidden="true" className={cn('size-2 shrink-0 rounded-full', DOT[level])} />
            <span className={cn('text-xs font-medium', TEXT[level])}>{label}</span>
        </span>
    );
}

/** A legend for an index page, so the dots are decodable on first encounter. */
export function ConfidentialityLegend({ items, className }: { items: { level: Confidentiality; label: string }[]; className?: string }) {
    return (
        <ul className={cn('flex flex-wrap items-center gap-x-4 gap-y-1', className)}>
            {items.map(({ level, label }) => (
                <li key={level} className="inline-flex items-center gap-1.5">
                    <span aria-hidden="true" className={cn('size-2 shrink-0 rounded-full', DOT[level])} />
                    <span className="text-2xs text-ink-subtle">{label}</span>
                </li>
            ))}
        </ul>
    );
}

/** Escape hatch for surfaces that need the coded colour on arbitrary content. */
export function ConfidentialityTint({
    confidentiality,
    children,
    className,
}: {
    confidentiality: string | null | undefined;
    children: ReactNode;
    className?: string;
}) {
    return <span className={cn(TEXT[normalizeConfidentiality(confidentiality)], className)}>{children}</span>;
}
