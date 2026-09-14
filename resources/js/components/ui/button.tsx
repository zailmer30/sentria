import { cn } from '@/lib/utils';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

/**
 * `primary` is the national blue, and it is the only blue on the page — so on
 * any given surface exactly one control should wear it. `live` is the national
 * red and is reserved for actions that are irreversible on the floor: opening
 * voting, adjourning. `danger` destroys something.
 *
 * `floor` size clears the touch minimum for chamber tablets.
 *
 * `default`, `outline` and `destructive` are shadcn's names, kept as aliases so
 * upstream blocks paste in without a rewrite. New code should say what it means
 * and use the Sentria names.
 */
const buttonVariants = cva(
    [
        'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-[var(--radius-md)] border',
        'font-medium whitespace-nowrap transition-[background-color,border-color,color,box-shadow] duration-[var(--duration-fast)]',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-focus)]',
        'disabled:pointer-events-none disabled:opacity-45 disabled:shadow-none',
        '[&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    ],
    {
        variants: {
            variant: {
                primary:
                    'border-accent bg-accent text-[var(--color-accent-on)] shadow-[var(--shadow-xs)] hover:border-[var(--color-accent-hover)] hover:bg-[var(--color-accent-hover)]',
                /** Deep navy used on register pages for the primary row action. */
                plate: 'border-[var(--color-floor-plate)] bg-[var(--color-floor-plate)] text-[var(--color-floor-ink)] shadow-none hover:border-[rgb(24,41,74)] hover:bg-[rgb(24,41,74)]',
                secondary:
                    'border-line-control bg-surface text-ink shadow-[var(--shadow-xs)] hover:bg-canvas-sunk',
                ghost: 'border-transparent bg-transparent text-ink-muted hover:bg-canvas-sunk hover:text-ink',
                live: 'border-live bg-live text-[var(--color-live-on)] shadow-[var(--shadow-xs)] hover:border-[var(--color-live-hover)] hover:bg-[var(--color-live-hover)]',
                danger: 'border-critical bg-critical text-[var(--color-critical-on)] shadow-[var(--shadow-xs)] hover:brightness-110',
                link: 'h-auto border-transparent p-0 text-accent underline decoration-[var(--color-accent-line)] underline-offset-2 hover:decoration-accent',

                /* shadcn aliases */
                default:
                    'border-accent bg-accent text-[var(--color-accent-on)] shadow-[var(--shadow-xs)] hover:border-[var(--color-accent-hover)] hover:bg-[var(--color-accent-hover)]',
                outline:
                    'border-line-control bg-surface text-ink shadow-[var(--shadow-xs)] hover:bg-canvas-sunk',
                destructive:
                    'border-critical bg-critical text-[var(--color-critical-on)] shadow-[var(--shadow-xs)] hover:brightness-110',
            },
            size: {
                sm: 'h-8 px-2.5 text-xs',
                default: 'h-9 px-3.5 text-sm',
                lg: 'h-10 px-4 text-sm',
                /** Chamber tablets: 48px, comfortably past the touch minimum. */
                floor: 'h-12 px-5 text-md',
                icon: 'size-9',
                'icon-sm': 'size-8',
                'icon-floor': 'size-12',
            },
        },
        defaultVariants: {
            variant: 'secondary',
            size: 'default',
        },
    },
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, asChild = false, ...props }, ref) => {
        const Comp = asChild ? Slot : 'button';

        return (
            <Comp
                data-slot="button"
                className={cn(buttonVariants({ variant, size }), className)}
                ref={ref}
                {...props}
            />
        );
    },
);
Button.displayName = 'Button';

export { buttonVariants };
