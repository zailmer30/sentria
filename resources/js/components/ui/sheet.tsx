import { cn } from '@/lib/utils';
import * as SheetPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import * as React from 'react';

/**
 * The edge panel: the nav rail below the large breakpoint, and any secondary
 * surface that should not take the whole screen. Radix handles the focus trap
 * and the scroll lock, which is why the mobile rail is built on it rather than
 * on a hand-rolled drawer.
 */

function Sheet(props: React.ComponentProps<typeof SheetPrimitive.Root>) {
    return <SheetPrimitive.Root data-slot="sheet" {...props} />;
}

function SheetTrigger(props: React.ComponentProps<typeof SheetPrimitive.Trigger>) {
    return <SheetPrimitive.Trigger data-slot="sheet-trigger" {...props} />;
}

function SheetClose(props: React.ComponentProps<typeof SheetPrimitive.Close>) {
    return <SheetPrimitive.Close data-slot="sheet-close" {...props} />;
}

function SheetOverlay({ className, ...props }: React.ComponentProps<typeof SheetPrimitive.Overlay>) {
    return (
        <SheetPrimitive.Overlay
            data-slot="sheet-overlay"
            className={cn(
                'fixed inset-0 z-40 bg-[var(--color-scrim)] backdrop-blur-[1px]',
                'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0',
                className,
            )}
            {...props}
        />
    );
}

function SheetContent({
    className,
    children,
    side = 'right',
    showClose = true,
    closeLabel = 'Close',
    ...props
}: React.ComponentProps<typeof SheetPrimitive.Content> & {
    side?: 'top' | 'right' | 'bottom' | 'left';
    showClose?: boolean;
    closeLabel?: string;
}) {
    return (
        <SheetPrimitive.Portal>
            <SheetOverlay />
            <SheetPrimitive.Content
                data-slot="sheet-content"
                className={cn(
                    'fixed z-50 flex flex-col bg-surface shadow-[var(--shadow-lg)] transition ease-[var(--ease-out-quint)]',
                    'data-[state=open]:animate-in data-[state=closed]:animate-out',
                    side === 'right' &&
                        'inset-y-0 right-0 h-full w-80 max-w-[85vw] border-l border-line data-[state=open]:slide-in-from-right data-[state=closed]:slide-out-to-right',
                    side === 'left' &&
                        'inset-y-0 left-0 h-full w-80 max-w-[85vw] border-r border-line data-[state=open]:slide-in-from-left data-[state=closed]:slide-out-to-left',
                    side === 'top' &&
                        'inset-x-0 top-0 h-auto border-b border-line data-[state=open]:slide-in-from-top data-[state=closed]:slide-out-to-top',
                    side === 'bottom' &&
                        'inset-x-0 bottom-0 h-auto border-t border-line data-[state=open]:slide-in-from-bottom data-[state=closed]:slide-out-to-bottom',
                    className,
                )}
                {...props}
            >
                {children}
                {showClose ? (
                    <SheetPrimitive.Close
                        aria-label={closeLabel}
                        className="absolute top-3 right-3 z-10 inline-flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-ink-muted transition-colors duration-[var(--duration-fast)] hover:bg-canvas-sunk hover:text-ink"
                    >
                        <X aria-hidden="true" strokeWidth={2} className="size-4" />
                    </SheetPrimitive.Close>
                ) : null}
            </SheetPrimitive.Content>
        </SheetPrimitive.Portal>
    );
}

function SheetHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sheet-header"
            className={cn('flex flex-col gap-1 border-b border-line px-4 py-3', className)}
            {...props}
        />
    );
}

function SheetFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sheet-footer"
            className={cn('mt-auto flex flex-wrap gap-2 border-t border-line px-4 py-3', className)}
            {...props}
        />
    );
}

function SheetTitle({ className, ...props }: React.ComponentProps<typeof SheetPrimitive.Title>) {
    return (
        <SheetPrimitive.Title
            data-slot="sheet-title"
            className={cn('text-sm font-semibold text-ink', className)}
            {...props}
        />
    );
}

function SheetDescription({
    className,
    ...props
}: React.ComponentProps<typeof SheetPrimitive.Description>) {
    return (
        <SheetPrimitive.Description
            data-slot="sheet-description"
            className={cn('text-xs text-ink-muted', className)}
            {...props}
        />
    );
}

export {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetOverlay,
    SheetTitle,
    SheetTrigger,
};
