import { cn } from '@/lib/utils';
import * as React from 'react';

/**
 * The bare shadcn table. `Register` in `register.tsx` composes this into the
 * dense, sticky-headed record list that is this product's main event; reach for
 * that first. Use `Table` directly only for small tabulations inside a card.
 *
 * Unlike upstream, the element carries no scroll container of its own: the
 * caller owns overflow, because a wrapper that scrolls in either axis captures
 * a sticky head and stops it from tracking the page.
 */

function Table({ className, ...props }: React.ComponentProps<'table'>) {
    return (
        <table
            data-slot="table"
            className={cn('w-full border-collapse text-left text-sm', className)}
            {...props}
        />
    );
}

function TableHeader({ className, ...props }: React.ComponentProps<'thead'>) {
    return (
        <thead
            data-slot="table-header"
            className={cn('[&_tr]:border-b [&_tr]:border-line-strong', className)}
            {...props}
        />
    );
}

function TableBody({ className, ...props }: React.ComponentProps<'tbody'>) {
    return (
        <tbody
            data-slot="table-body"
            className={cn('[&_tr:last-child]:border-0', className)}
            {...props}
        />
    );
}

function TableFooter({ className, ...props }: React.ComponentProps<'tfoot'>) {
    return (
        <tfoot
            data-slot="table-footer"
            className={cn('border-t border-line bg-surface-alt font-medium', className)}
            {...props}
        />
    );
}

function TableRow({ className, ...props }: React.ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            className={cn(
                'border-b border-line transition-colors duration-[var(--duration-fast)] hover:bg-canvas-sunk',
                className,
            )}
            {...props}
        />
    );
}

function TableHead({ className, ...props }: React.ComponentProps<'th'>) {
    return (
        <th
            data-slot="table-head"
            className={cn(
                'px-3 py-3 text-left align-middle text-xs font-semibold tracking-[0.05em] text-ink-muted uppercase',
                className,
            )}
            {...props}
        />
    );
}

function TableCell({ className, ...props }: React.ComponentProps<'td'>) {
    return (
        <td
            data-slot="table-cell"
            className={cn('px-3 py-2 align-middle text-ink-muted', className)}
            {...props}
        />
    );
}

function TableCaption({ className, ...props }: React.ComponentProps<'caption'>) {
    return (
        <caption
            data-slot="table-caption"
            className={cn('mt-3 text-xs text-ink-subtle', className)}
            {...props}
        />
    );
}

export { Table, TableBody, TableCaption, TableCell, TableFooter, TableHead, TableHeader, TableRow };
