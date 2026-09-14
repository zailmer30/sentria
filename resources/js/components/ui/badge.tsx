import { cn } from '@/lib/utils';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

/**
 * The bare badge. `StatusChip` in `status.tsx` is the one to reach for when the
 * thing being labelled is a state in a state machine — it carries the marker
 * that distinguishes "still moving" from "settled" without relying on colour.
 * This is for everything else: counts, types, tags.
 */
const badgeVariants = cva(
    [
        'inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden',
        'rounded-[var(--radius-xs)] border px-2 py-0.5 text-2xs font-medium whitespace-nowrap',
        '[&>svg]:pointer-events-none [&>svg]:size-3',
    ],
    {
        variants: {
            variant: {
                default: 'border-transparent bg-accent text-[var(--color-accent-on)]',
                secondary: 'border-line bg-canvas-sunk text-ink-muted',
                outline: 'border-line-strong bg-transparent text-ink-muted',
                success: 'border-[var(--color-success-line)] bg-success-soft text-success',
                warning: 'border-[var(--color-warning-line)] bg-warning-soft text-warning',
                destructive: 'border-[var(--color-critical-line)] bg-critical-soft text-critical',
                live: 'border-[var(--color-live-line)] bg-live-soft text-[var(--color-live-ink)]',
            },
        },
        defaultVariants: {
            variant: 'secondary',
        },
    },
);

function Badge({
    className,
    variant,
    asChild = false,
    ...props
}: React.ComponentProps<'span'> & VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'span';

    return (
        <Comp data-slot="badge" className={cn(badgeVariants({ variant }), className)} {...props} />
    );
}

export { Badge, badgeVariants };
