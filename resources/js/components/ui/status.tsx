import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * Every record in this product is somewhere in a state machine, and the chip is
 * how that fact is stated. It is the shadcn badge carrying two extra pieces of
 * information that colour alone could not:
 *
 *   the marker — hollow means still in motion, filled means settled
 *   the tone   — and only `live` may take the national red
 *
 * Because the marker carries the in-motion/settled distinction on its own, the
 * chip never depends on colour alone, which also keeps it legible to anyone
 * scanning a register of forty rows for the three that still need them.
 */

export type StatusTone = 'draft' | 'moving' | 'review' | 'live' | 'final' | 'closed' | 'blocked';

const TONE: Record<StatusTone, string> = {
    draft: 'border-line-strong bg-canvas-sunk text-ink-muted',
    moving: 'border-[var(--color-info-line)] bg-info-soft text-info',
    review: 'border-[var(--color-warning-line)] bg-warning-soft text-warning',
    live: 'border-[var(--color-live-line)] bg-live-soft text-[var(--color-live-ink)]',
    final: 'border-[var(--color-success-line)] bg-success-soft text-success',
    closed: 'border-line bg-canvas-sunk text-ink-faint',
    blocked: 'border-[var(--color-critical-line)] bg-critical-soft text-critical',
};

/** Settled states take a filled marker; anything still moving stays hollow. */
const SETTLED: StatusTone[] = ['final', 'closed', 'blocked'];

type StatusChipProps = {
    /** The human label. Always server-provided (`status_label`) or translated. */
    children: ReactNode;
    tone?: StatusTone;
    /** Overrides the marker when the state's finality differs from its tone. */
    settled?: boolean;
    /** Plays the advance wipe. Set only when the state has just changed. */
    advanced?: boolean;
    size?: 'sm' | 'default';
    className?: string;
    title?: string;
};

export function StatusChip({
    children,
    tone = 'draft',
    settled,
    advanced = false,
    size = 'default',
    className,
    title,
}: StatusChipProps) {
    const filled = settled ?? SETTLED.includes(tone);

    return (
        <Badge
            title={title}
            className={cn(
                'max-w-full gap-1.5',
                size === 'sm' ? 'px-1.5 py-0.5 text-2xs' : 'px-2 py-0.5 text-xs',
                TONE[tone],
                advanced && 'animate-advance',
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'size-1.5 shrink-0 rounded-[1px] border border-current',
                    filled && 'bg-current',
                    tone === 'live' && 'animate-live-pulse rounded-full bg-current',
                )}
            />
            <span className="truncate">{children}</span>
        </Badge>
    );
}

/**
 * The bare live marker, for places that already say "in session" in words and
 * only need the pulse: a nav item, a header, a register row.
 */
export function LiveDot({ className }: { className?: string }) {
    return (
        <span
            aria-hidden="true"
            className={cn('animate-live-pulse inline-block size-1.5 shrink-0 rounded-full bg-live', className)}
        />
    );
}

/**
 * Backend states arrive as slugs from the four state machines. This maps them
 * onto chip tones so one vocabulary covers sessions, documents, minutes and
 * publications. Unknown slugs fall back to `draft` rather than inventing a
 * state, because a wrong tone reads as a wrong fact.
 */
export function toneForState(state: string | null | undefined): StatusTone {
    if (!state) {
        return 'draft';
    }

    const key = state.toLowerCase().replaceAll('_', '-');

    if (key === 'in-session' || key === 'voting' || key === 'reading-deliberation') {
        return 'live';
    }

    if (
        key === 'finalized' ||
        key === 'final-minutes' ||
        key === 'final-document' ||
        key === 'published' ||
        key === 'public-publication' ||
        key === 'approved' ||
        key === 'adopted' ||
        key === 'enacted' ||
        key === 'registered'
    ) {
        return 'final';
    }

    if (
        key === 'archived' ||
        key === 'archive' ||
        key === 'adjourned' ||
        key === 'withdrawn' ||
        key === 'repealed'
    ) {
        return 'closed';
    }

    if (key === 'rejected' || key === 'suspended' || key === 'failed' || key === 'vetoed') {
        return 'blocked';
    }

    if (
        key === 'secretariat-review' ||
        key === 'publication-review' ||
        key === 'committee-review' ||
        key === 'minutes-for-review' ||
        key === 'review' ||
        key === 'in-review' ||
        key === 'chair-review' ||
        key === 'approval' ||
        key === 'ai-draft'
    ) {
        return 'review';
    }

    if (key === 'draft' || key === 'submitted' || key === 'pending' || key === 'internal-document' || key === 'returned-for-revision') {
        return 'draft';
    }

    return 'moving';
}
