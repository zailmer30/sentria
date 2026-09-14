import { cn } from '@/lib/utils';
import * as ToggleGroupPrimitive from '@radix-ui/react-toggle-group';
import * as React from 'react';

/**
 * The segmented control. Used for range selection (7d / 30d / 90d) and for any
 * other small, mutually exclusive choice that changes what a surface is showing
 * rather than what it means. The dashboard chart uses 7d / 30d / Ytd.
 */

const ToggleGroupContext = React.createContext<{ size: 'sm' | 'default' }>({ size: 'default' });

function ToggleGroup({
    className,
    size = 'default',
    children,
    ...props
}: React.ComponentProps<typeof ToggleGroupPrimitive.Root> & { size?: 'sm' | 'default' }) {
    return (
        <ToggleGroupPrimitive.Root
            data-slot="toggle-group"
            className={cn(
                'inline-flex w-fit items-center gap-0.5 rounded-[var(--radius-md)] border border-line bg-canvas-sunk p-0.5',
                className,
            )}
            {...props}
        >
            <ToggleGroupContext.Provider value={{ size }}>{children}</ToggleGroupContext.Provider>
        </ToggleGroupPrimitive.Root>
    );
}

function ToggleGroupItem({
    className,
    children,
    ...props
}: React.ComponentProps<typeof ToggleGroupPrimitive.Item>) {
    const { size } = React.useContext(ToggleGroupContext);

    return (
        <ToggleGroupPrimitive.Item
            data-slot="toggle-group-item"
            className={cn(
                'inline-flex items-center justify-center gap-1.5 rounded-[var(--radius-sm)] font-medium whitespace-nowrap text-ink-muted',
                size === 'sm' ? 'h-6 min-w-8 px-2 text-2xs' : 'h-7 min-w-9 px-2.5 text-xs',
                'transition-colors duration-[var(--duration-fast)] hover:text-ink',
                'data-[state=on]:bg-surface data-[state=on]:text-ink data-[state=on]:shadow-[var(--shadow-xs)]',
                'disabled:pointer-events-none disabled:opacity-45',
                '[&_svg]:pointer-events-none [&_svg]:size-3.5',
                className,
            )}
            {...props}
        >
            {children}
        </ToggleGroupPrimitive.Item>
    );
}

export { ToggleGroup, ToggleGroupItem };
