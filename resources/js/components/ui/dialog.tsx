import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import * as React from 'react';

/**
 * Modals are a last resort here — most tasks in this system belong inline. This
 * exists for the ones that genuinely need protected focus: correcting a
 * transcript segment, confirming an irreversible transition.
 *
 * The scrim is deliberately strong and blurred: a floor surface behind a dialog
 * must stop competing for attention, and on a tablet in a dim chamber a weak
 * scrim reads as two live surfaces at once.
 */

export const Dialog = DialogPrimitive.Root;
export const DialogTrigger = DialogPrimitive.Trigger;
export const DialogClose = DialogPrimitive.Close;

export const DialogContent = React.forwardRef<
    React.ElementRef<typeof DialogPrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content> & {
        title: string;
        description?: string;
        bodyClassName?: string;
        /** Extra chrome between the title bar and the body — a stepper, for example. */
        headerExtra?: React.ReactNode;
        /** Pinned below the scrollable body, outside the padded content. */
        footer?: React.ReactNode;
        titleClassName?: string;
        size?: 'default' | 'lg';
    }
>(({ className, bodyClassName, children, title, description, headerExtra, footer, titleClassName, size = 'default', ...props }, ref) => {
    const { t } = useTranslations();
    const large = size === 'lg';

    return (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-[var(--color-scrim-strong)] backdrop-blur-[2px] data-[state=open]:animate-in data-[state=open]:fade-in-0" />
            <DialogPrimitive.Content
                ref={ref}
                className={cn(
                    'fixed top-1/2 left-1/2 z-50 flex w-[calc(100vw-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden',
                    'rounded-[var(--radius-lg)] border border-line bg-surface-raised shadow-[var(--shadow-lg)]',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    large && 'h-[min(42rem,calc(100vh-2rem))] max-w-2xl rounded-[var(--radius-md)]',
                    className,
                )}
                {...props}
            >
                <div
                    className={cn(
                        'flex shrink-0 items-start justify-between gap-4 border-b border-line',
                        large ? 'px-5 py-4' : 'px-4 py-3',
                    )}
                >
                    <div className="min-w-0">
                        <DialogPrimitive.Title
                            className={cn(
                                'font-semibold text-ink',
                                large ? 'text-lg tracking-tight' : 'text-sm',
                                titleClassName,
                            )}
                        >
                            {title}
                        </DialogPrimitive.Title>
                        {description ? (
                            <DialogPrimitive.Description className="mt-1 text-sm text-ink-subtle">
                                {description}
                            </DialogPrimitive.Description>
                        ) : null}
                    </div>
                    <DialogPrimitive.Close asChild>
                        <Button variant="ghost" size="icon-sm" aria-label={t('actions.close')} className="-my-0.5 -mr-1">
                            <X aria-hidden="true" strokeWidth={2} />
                        </Button>
                    </DialogPrimitive.Close>
                </div>

                {headerExtra}

                <div className={cn('min-h-0 flex-1 overflow-y-auto px-4 py-4', bodyClassName)}>{children}</div>

                {footer}
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
});
DialogContent.displayName = 'DialogContent';

export function DialogFooter({ children, className }: { children: React.ReactNode; className?: string }) {
    return <div className={cn('mt-4 flex flex-wrap items-center justify-end gap-2', className)}>{children}</div>;
}
