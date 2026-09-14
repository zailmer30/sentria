import { cn } from '@/lib/utils';
import * as React from 'react';

/**
 * One control vocabulary for every form in the product: 36px tall, the same
 * radius as a button so a field and the control beside it sit on one line
 * without arguing, and a border that clears 3:1 against its surface so the
 * field's boundary is visible rather than implied. An invalid field is marked
 * by border colour *and* by its message, never by colour alone.
 */
const fieldBase = [
    'w-full rounded-[var(--radius-md)] border border-line-control bg-surface text-ink',
    'transition-[border-color,box-shadow] duration-[var(--duration-fast)]',
    'placeholder:text-ink-subtle',
    'focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-focus)]',
    'disabled:cursor-not-allowed disabled:border-line-strong disabled:bg-canvas-sunk disabled:text-ink-faint',
    'aria-[invalid=true]:border-critical aria-[invalid=true]:bg-critical-soft',
];

export const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'>>(
    ({ className, type, ...props }, ref) => (
        <input
            type={type}
            ref={ref}
            className={cn(
                fieldBase,
                'h-9 px-2.5 py-1.5 text-sm',
                'file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-ink',
                className,
            )}
            {...props}
        />
    ),
);
Input.displayName = 'Input';

export const Textarea = React.forwardRef<HTMLTextAreaElement, React.ComponentProps<'textarea'>>(
    ({ className, rows = 6, ...props }, ref) => (
        <textarea ref={ref} rows={rows} className={cn(fieldBase, 'px-2.5 py-2 text-sm leading-6', className)} {...props} />
    ),
);
Textarea.displayName = 'Textarea';

export const Checkbox = React.forwardRef<HTMLInputElement, Omit<React.ComponentProps<'input'>, 'type'>>(
    ({ className, ...props }, ref) => (
        <input type="checkbox" ref={ref} className={cn('input-checkbox', className)} {...props} />
    ),
);
Checkbox.displayName = 'Checkbox';
