import { cn } from '@/lib/utils';
import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { Check, ChevronRight, Circle } from 'lucide-react';
import * as React from 'react';

function DropdownMenu(props: React.ComponentProps<typeof DropdownMenuPrimitive.Root>) {
    return <DropdownMenuPrimitive.Root data-slot="dropdown-menu" {...props} />;
}

function DropdownMenuTrigger(props: React.ComponentProps<typeof DropdownMenuPrimitive.Trigger>) {
    return <DropdownMenuPrimitive.Trigger data-slot="dropdown-menu-trigger" {...props} />;
}

function DropdownMenuGroup(props: React.ComponentProps<typeof DropdownMenuPrimitive.Group>) {
    return <DropdownMenuPrimitive.Group data-slot="dropdown-menu-group" {...props} />;
}

function DropdownMenuContent({
    className,
    sideOffset = 6,
    ...props
}: React.ComponentProps<typeof DropdownMenuPrimitive.Content>) {
    return (
        <DropdownMenuPrimitive.Portal>
            <DropdownMenuPrimitive.Content
                data-slot="dropdown-menu-content"
                sideOffset={sideOffset}
                className={cn(
                    'z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-44 overflow-y-auto p-1',
                    'rounded-[var(--radius-md)] border border-line bg-surface-raised text-ink shadow-[var(--shadow-lg)]',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0',
                    className,
                )}
                {...props}
            />
        </DropdownMenuPrimitive.Portal>
    );
}

function DropdownMenuItem({
    className,
    inset,
    variant = 'default',
    ...props
}: React.ComponentProps<typeof DropdownMenuPrimitive.Item> & {
    inset?: boolean;
    variant?: 'default' | 'destructive';
}) {
    return (
        <DropdownMenuPrimitive.Item
            data-slot="dropdown-menu-item"
            data-inset={inset}
            data-variant={variant}
            className={cn(
                'relative flex cursor-default items-center gap-2 rounded-[var(--radius-xs)] px-2 py-1.5 text-sm text-ink-muted outline-none select-none',
                'data-[highlighted]:bg-canvas-sunk data-[highlighted]:text-ink',
                'data-[variant=destructive]:text-critical data-[variant=destructive]:data-[highlighted]:bg-critical-soft data-[variant=destructive]:data-[highlighted]:text-critical',
                'data-[disabled]:pointer-events-none data-[disabled]:opacity-45',
                'data-[inset]:pl-8',
                '[&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
                className,
            )}
            {...props}
        />
    );
}

function DropdownMenuCheckboxItem({
    className,
    children,
    checked,
    ...props
}: React.ComponentProps<typeof DropdownMenuPrimitive.CheckboxItem>) {
    return (
        <DropdownMenuPrimitive.CheckboxItem
            data-slot="dropdown-menu-checkbox-item"
            checked={checked}
            className={cn(
                'relative flex cursor-default items-center gap-2 rounded-[var(--radius-xs)] py-1.5 pr-2 pl-8 text-sm text-ink-muted outline-none select-none',
                'data-[highlighted]:bg-canvas-sunk data-[highlighted]:text-ink',
                'data-[disabled]:pointer-events-none data-[disabled]:opacity-45',
                className,
            )}
            {...props}
        >
            <span className="absolute left-2 flex size-3.5 items-center justify-center">
                <DropdownMenuPrimitive.ItemIndicator>
                    <Check aria-hidden="true" strokeWidth={2} className="size-3.5 text-accent" />
                </DropdownMenuPrimitive.ItemIndicator>
            </span>
            {children}
        </DropdownMenuPrimitive.CheckboxItem>
    );
}

function DropdownMenuRadioGroup(
    props: React.ComponentProps<typeof DropdownMenuPrimitive.RadioGroup>,
) {
    return <DropdownMenuPrimitive.RadioGroup data-slot="dropdown-menu-radio-group" {...props} />;
}

function DropdownMenuRadioItem({
    className,
    children,
    ...props
}: React.ComponentProps<typeof DropdownMenuPrimitive.RadioItem>) {
    return (
        <DropdownMenuPrimitive.RadioItem
            data-slot="dropdown-menu-radio-item"
            className={cn(
                'relative flex cursor-default items-center gap-2 rounded-[var(--radius-xs)] py-1.5 pr-2 pl-8 text-sm text-ink-muted outline-none select-none',
                'data-[highlighted]:bg-canvas-sunk data-[highlighted]:text-ink',
                'data-[disabled]:pointer-events-none data-[disabled]:opacity-45',
                className,
            )}
            {...props}
        >
            <span className="absolute left-2 flex size-3.5 items-center justify-center">
                <DropdownMenuPrimitive.ItemIndicator>
                    <Circle aria-hidden="true" className="size-2 fill-current text-accent" />
                </DropdownMenuPrimitive.ItemIndicator>
            </span>
            {children}
        </DropdownMenuPrimitive.RadioItem>
    );
}

function DropdownMenuLabel({
    className,
    inset,
    ...props
}: React.ComponentProps<typeof DropdownMenuPrimitive.Label> & { inset?: boolean }) {
    return (
        <DropdownMenuPrimitive.Label
            data-slot="dropdown-menu-label"
            data-inset={inset}
            className={cn('px-2 py-1.5 text-sm font-medium text-ink data-[inset]:pl-8', className)}
            {...props}
        />
    );
}

function DropdownMenuSeparator({
    className,
    ...props
}: React.ComponentProps<typeof DropdownMenuPrimitive.Separator>) {
    return (
        <DropdownMenuPrimitive.Separator
            data-slot="dropdown-menu-separator"
            className={cn('-mx-1 my-1 h-px bg-line', className)}
            {...props}
        />
    );
}

function DropdownMenuShortcut({ className, ...props }: React.ComponentProps<'span'>) {
    return (
        <span
            data-slot="dropdown-menu-shortcut"
            className={cn('ml-auto font-mono text-2xs tracking-widest text-ink-faint', className)}
            {...props}
        />
    );
}

function DropdownMenuSub(props: React.ComponentProps<typeof DropdownMenuPrimitive.Sub>) {
    return <DropdownMenuPrimitive.Sub data-slot="dropdown-menu-sub" {...props} />;
}

function DropdownMenuSubTrigger({
    className,
    inset,
    children,
    ...props
}: React.ComponentProps<typeof DropdownMenuPrimitive.SubTrigger> & { inset?: boolean }) {
    return (
        <DropdownMenuPrimitive.SubTrigger
            data-slot="dropdown-menu-sub-trigger"
            data-inset={inset}
            className={cn(
                'flex cursor-default items-center rounded-[var(--radius-xs)] px-2 py-1.5 text-sm text-ink-muted outline-none select-none',
                'data-[highlighted]:bg-canvas-sunk data-[highlighted]:text-ink data-[state=open]:bg-canvas-sunk',
                'data-[inset]:pl-8',
                className,
            )}
            {...props}
        >
            {children}
            <ChevronRight aria-hidden="true" strokeWidth={1.75} className="ml-auto size-3.5" />
        </DropdownMenuPrimitive.SubTrigger>
    );
}

function DropdownMenuSubContent({
    className,
    ...props
}: React.ComponentProps<typeof DropdownMenuPrimitive.SubContent>) {
    return (
        <DropdownMenuPrimitive.SubContent
            data-slot="dropdown-menu-sub-content"
            className={cn(
                'z-50 min-w-32 overflow-hidden rounded-[var(--radius-md)] border border-line bg-surface-raised p-1 text-ink shadow-[var(--shadow-lg)]',
                className,
            )}
            {...props}
        />
    );
}

export {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
};
