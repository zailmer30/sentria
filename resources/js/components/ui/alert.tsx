import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

/**
 * The bare alert. `Notice` in `notice.tsx` is the product-facing one — it adds
 * the `restricted` and `live` tones this system needs and is what pages should
 * reach for. This exists so shadcn blocks drop in without rewriting.
 */
const alertVariants = cva(
    'relative w-full rounded-[var(--radius-md)] border px-4 py-3 text-sm [&>svg]:size-4 [&>svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'border-line bg-surface text-ink',
                info: 'border-[var(--color-info-line)] bg-info-soft text-info',
                warning: 'border-[var(--color-warning-line)] bg-warning-soft text-warning',
                destructive: 'border-[var(--color-critical-line)] bg-critical-soft text-critical',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Alert({
    className,
    variant,
    ...props
}: React.ComponentProps<'div'> & VariantProps<typeof alertVariants>) {
    return (
        <div
            data-slot="alert"
            role="alert"
            className={cn(alertVariants({ variant }), className)}
            {...props}
        />
    );
}

function AlertTitle({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-title"
            className={cn('font-medium tracking-tight', className)}
            {...props}
        />
    );
}

function AlertDescription({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-description"
            className={cn('text-sm opacity-90', className)}
            {...props}
        />
    );
}

export { Alert, AlertDescription, AlertTitle, alertVariants };
