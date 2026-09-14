import { cn } from '@/lib/utils';
import { Slot } from '@radix-ui/react-slot';
import { ChevronRight } from 'lucide-react';
import * as React from 'react';

function Breadcrumb(props: React.ComponentProps<'nav'>) {
    return <nav data-slot="breadcrumb" aria-label="breadcrumb" {...props} />;
}

function BreadcrumbList({ className, ...props }: React.ComponentProps<'ol'>) {
    return (
        <ol
            data-slot="breadcrumb-list"
            className={cn('flex flex-wrap items-center gap-1.5 text-xs text-ink-muted', className)}
            {...props}
        />
    );
}

function BreadcrumbItem({ className, ...props }: React.ComponentProps<'li'>) {
    return (
        <li
            data-slot="breadcrumb-item"
            className={cn('inline-flex items-center gap-1.5', className)}
            {...props}
        />
    );
}

function BreadcrumbLink({
    className,
    asChild = false,
    ...props
}: React.ComponentProps<'a'> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'a';

    return (
        <Comp
            data-slot="breadcrumb-link"
            className={cn(
                'transition-colors duration-[var(--duration-fast)] hover:text-ink',
                className,
            )}
            {...props}
        />
    );
}

function BreadcrumbPage({ className, ...props }: React.ComponentProps<'span'>) {
    return (
        <span
            data-slot="breadcrumb-page"
            aria-current="page"
            className={cn('font-medium text-ink', className)}
            {...props}
        />
    );
}

function BreadcrumbSeparator({ children, className, ...props }: React.ComponentProps<'li'>) {
    return (
        <li
            data-slot="breadcrumb-separator"
            role="presentation"
            aria-hidden="true"
            className={cn('text-ink-faint [&>svg]:size-3', className)}
            {...props}
        >
            {children ?? <ChevronRight strokeWidth={1.75} />}
        </li>
    );
}

export {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
};
