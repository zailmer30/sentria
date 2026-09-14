import { cn } from '@/lib/utils';
import { Slot } from '@radix-ui/react-slot';
import * as React from 'react';

/**
 * The shadcn card, tuned to this system: a hairline, a wide faint shadow and a
 * radius soft enough that the card reads as an object standing on the canvas.
 *
 * Most of the product should reach for `Panel` instead, which adds the states
 * a legislative record needs (live, raised). `Card` is the bare container for
 * dashboard widgets and anything composed from upstream shadcn blocks.
 */

function Card({
    className,
    asChild = false,
    ...props
}: React.ComponentProps<'div'> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'div';

    return (
        <Comp
            data-slot="card"
            className={cn(
                'flex flex-col rounded-[var(--radius-lg)] border border-line bg-surface text-ink shadow-[var(--shadow-xs)]',
                className,
            )}
            {...props}
        />
    );
}

function CardHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-header"
            className={cn(
                'flex flex-wrap items-start justify-between gap-x-4 gap-y-2 px-5 pt-5',
                className,
            )}
            {...props}
        />
    );
}

function CardTitle({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-title"
            className={cn('text-sm font-semibold text-ink', className)}
            {...props}
        />
    );
}

function CardDescription({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-description"
            className={cn('text-xs text-ink-muted', className)}
            {...props}
        />
    );
}

function CardAction({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-action"
            className={cn('flex shrink-0 items-center gap-1.5', className)}
            {...props}
        />
    );
}

function CardContent({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card-content" className={cn('px-5 py-5', className)} {...props} />;
}

function CardFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-footer"
            className={cn(
                'flex flex-wrap items-center gap-2 border-t border-line px-5 py-3',
                className,
            )}
            {...props}
        />
    );
}

export { Card, CardAction, CardContent, CardDescription, CardFooter, CardHeader, CardTitle };
