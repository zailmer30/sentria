import { cn } from '@/lib/utils';

/**
 * Loading shows the shape of the record that is coming, not a spinner in the
 * middle of the page.
 */
export function Skeleton({ className }: { className?: string }) {
    return (
        <span aria-hidden="true" className={cn('block animate-pulse rounded-[var(--radius-xs)] bg-canvas-sunk', className)} />
    );
}

/** Placeholder rows shaped like the register they are replacing. */
export function SkeletonLines({ lines = 3, className }: { lines?: number; className?: string }) {
    return (
        <div className={cn('flex flex-col gap-2', className)}>
            {Array.from({ length: lines }, (_, index) => (
                <Skeleton key={index} className={cn('h-3', index === lines - 1 ? 'w-2/5' : 'w-full')} />
            ))}
        </div>
    );
}
