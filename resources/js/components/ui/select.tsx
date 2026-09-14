import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import * as SelectPrimitive from '@radix-ui/react-select';
import { Check, ChevronDown, ChevronUp, Search } from 'lucide-react';
import * as React from 'react';

/**
 * The shadcn select. Empty string is not a valid Radix item value, so optional
 * fields use `SELECT_NONE` and `SimpleSelect` maps it back to `''`.
 */

function Select(props: React.ComponentProps<typeof SelectPrimitive.Root>) {
    return <SelectPrimitive.Root data-slot="select" {...props} />;
}

function SelectGroup(props: React.ComponentProps<typeof SelectPrimitive.Group>) {
    return <SelectPrimitive.Group data-slot="select-group" {...props} />;
}

function SelectValue(props: React.ComponentProps<typeof SelectPrimitive.Value>) {
    return <SelectPrimitive.Value data-slot="select-value" {...props} />;
}

function SelectTrigger({
    className,
    size = 'default',
    children,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Trigger> & { size?: 'sm' | 'default' }) {
    return (
        <SelectPrimitive.Trigger
            data-slot="select-trigger"
            className={cn(
                'flex w-full items-center justify-between gap-2 rounded-[var(--radius-md)] border border-line-control bg-surface text-sm text-ink',
                size === 'sm' ? 'h-8 px-2.5' : 'h-9 px-3',
                'transition-[border-color,background-color,box-shadow] duration-[var(--duration-fast)] hover:bg-canvas-sunk',
                'focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-focus)]',
                'data-[placeholder]:text-ink-subtle disabled:pointer-events-none disabled:opacity-45',
                'aria-[invalid=true]:border-critical aria-[invalid=true]:bg-critical-soft',
                '[&>span]:truncate',
                className,
            )}
            {...props}
        >
            {children}
            <SelectPrimitive.Icon asChild>
                <ChevronDown aria-hidden="true" strokeWidth={1.75} className="size-4 shrink-0 text-ink-faint" />
            </SelectPrimitive.Icon>
        </SelectPrimitive.Trigger>
    );
}

function SelectContent({
    className,
    children,
    position = 'popper',
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Content>) {
    return (
        <SelectPrimitive.Portal>
            <SelectPrimitive.Content
                data-slot="select-content"
                position={position}
                className={cn(
                    'relative z-50 max-h-(--radix-select-content-available-height) min-w-32 overflow-hidden',
                    'rounded-[var(--radius-md)] border border-line bg-surface-raised text-ink shadow-[var(--shadow-lg)]',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0',
                    className,
                )}
                {...props}
            >
                <SelectScrollUpButton />
                <SelectPrimitive.Viewport
                    className={cn(
                        'p-1',
                        position === 'popper' &&
                            'h-(--radix-select-trigger-height) w-full min-w-(--radix-select-trigger-width) scroll-my-1',
                    )}
                >
                    {children}
                </SelectPrimitive.Viewport>
                <SelectScrollDownButton />
            </SelectPrimitive.Content>
        </SelectPrimitive.Portal>
    );
}

function SelectLabel({ className, ...props }: React.ComponentProps<typeof SelectPrimitive.Label>) {
    return (
        <SelectPrimitive.Label
            data-slot="select-label"
            className={cn('label-eyebrow px-2 py-1.5', className)}
            {...props}
        />
    );
}

function SelectItem({
    className,
    children,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Item>) {
    return (
        <SelectPrimitive.Item
            data-slot="select-item"
            className={cn(
                'relative flex w-full cursor-default items-center gap-2 rounded-[var(--radius-xs)] py-1.5 pr-8 pl-2 text-sm text-ink-muted outline-none select-none',
                'data-[highlighted]:bg-canvas-sunk data-[highlighted]:text-ink',
                'data-[state=checked]:font-medium data-[state=checked]:text-ink',
                'data-[disabled]:pointer-events-none data-[disabled]:opacity-45',
                className,
            )}
            {...props}
        >
            <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
            <span className="absolute right-2 flex size-3.5 items-center justify-center">
                <SelectPrimitive.ItemIndicator>
                    <Check aria-hidden="true" strokeWidth={2} className="size-3.5 text-accent" />
                </SelectPrimitive.ItemIndicator>
            </span>
        </SelectPrimitive.Item>
    );
}

function SelectSeparator({
    className,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Separator>) {
    return (
        <SelectPrimitive.Separator
            data-slot="select-separator"
            className={cn('-mx-1 my-1 h-px bg-line', className)}
            {...props}
        />
    );
}

function SelectScrollUpButton({
    className,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.ScrollUpButton>) {
    return (
        <SelectPrimitive.ScrollUpButton
            data-slot="select-scroll-up-button"
            className={cn('flex items-center justify-center py-1 text-ink-faint', className)}
            {...props}
        >
            <ChevronUp aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
        </SelectPrimitive.ScrollUpButton>
    );
}

function SelectScrollDownButton({
    className,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.ScrollDownButton>) {
    return (
        <SelectPrimitive.ScrollDownButton
            data-slot="select-scroll-down-button"
            className={cn('flex items-center justify-center py-1 text-ink-faint', className)}
            {...props}
        >
            <ChevronDown aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
        </SelectPrimitive.ScrollDownButton>
    );
}

/** Sentinel for an unselected optional field — Radix forbids `value=""`. */
export const SELECT_NONE = '__none';

export function selectValue(value: string): string {
    return value === '' ? SELECT_NONE : value;
}

export function parseSelectValue(value: string): string {
    return value === SELECT_NONE ? '' : value;
}

type SimpleSelectProps = {
    id?: string;
    value: string;
    onValueChange: (value: string) => void;
    items: { value: string; label: string }[];
    placeholder?: string;
    noneLabel?: string;
    disabled?: boolean;
    className?: string;
    'aria-invalid'?: boolean;
    'aria-label'?: string;
};

/** A labelled list of options on the shadcn select. */
export function SimpleSelect({
    id,
    value,
    onValueChange,
    items,
    placeholder,
    noneLabel,
    disabled,
    className,
    ...aria
}: SimpleSelectProps) {
    return (
        <Select
            value={selectValue(value)}
            onValueChange={(next) => onValueChange(parseSelectValue(next))}
            disabled={disabled}
        >
            <SelectTrigger id={id} className={className} {...aria}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {noneLabel ? <SelectItem value={SELECT_NONE}>{noneLabel}</SelectItem> : null}
                {items.map((item) => (
                    <SelectItem key={item.value} value={item.value}>
                        {item.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

type SearchableSelectItem = {
    value: string;
    label: string;
    /** Extra text matched by search (a series tag, for example). */
    keywords?: string;
};

type SearchableSelectProps = {
    id?: string;
    value: string;
    onValueChange: (value: string) => void;
    items: SearchableSelectItem[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyLabel?: string;
    disabled?: boolean;
    className?: string;
    'aria-invalid'?: boolean;
    'aria-label'?: string;
};

/**
 * A select that can be typed into. The closed control matches `SimpleSelect`;
 * opening it puts a search field above the list so a long catalogue can be
 * narrowed without scrolling the whole page.
 */
export function SearchableSelect({
    id,
    value,
    onValueChange,
    items,
    placeholder,
    searchPlaceholder,
    emptyLabel,
    disabled,
    className,
    ...aria
}: SearchableSelectProps) {
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [active, setActive] = React.useState(0);
    const searchRef = React.useRef<HTMLInputElement>(null);
    const selected = items.find((item) => item.value === value);

    const filtered = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) {
            return items;
        }

        return items.filter((item) => {
            const haystack = `${item.label} ${item.keywords ?? ''}`.toLowerCase();

            return haystack.includes(needle);
        });
    }, [items, query]);

    const activeIndex = filtered.length === 0 ? 0 : Math.min(active, filtered.length - 1);

    function close() {
        setOpen(false);
        setQuery('');
        setActive(0);
    }

    function choose(next: string) {
        onValueChange(next);
        close();
    }

    function onOpenChange(next: boolean) {
        setOpen(next);

        if (!next) {
            setQuery('');
            setActive(0);
        } else {
            setActive(0);
        }
    }

    function onSearchKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(Math.min(activeIndex + 1, Math.max(filtered.length - 1, 0)));

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(Math.max(activeIndex - 1, 0));

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            const item = filtered[activeIndex];

            if (item) {
                choose(item.value);
            }

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    }

    const listId = id ? `${id}-listbox` : undefined;

    return (
        <Popover modal open={open} onOpenChange={onOpenChange}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    id={id}
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={listId}
                    aria-haspopup="listbox"
                    disabled={disabled}
                    className={cn(
                        'flex w-full items-center justify-between gap-2 rounded-[var(--radius-md)] border border-line-control bg-surface text-sm text-ink',
                        'h-9 px-3',
                        'transition-[border-color,background-color,box-shadow] duration-[var(--duration-fast)] hover:bg-canvas-sunk',
                        'focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-focus)]',
                        'disabled:pointer-events-none disabled:opacity-45',
                        'aria-[invalid=true]:border-critical aria-[invalid=true]:bg-critical-soft',
                        !selected && 'text-ink-subtle',
                        className,
                    )}
                    {...aria}
                >
                    <span className="truncate">{selected?.label ?? placeholder}</span>
                    <ChevronDown aria-hidden="true" strokeWidth={1.75} className="size-4 shrink-0 text-ink-faint" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                sideOffset={4}
                className="z-[60] w-[var(--radix-popover-trigger-width)] p-0"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    searchRef.current?.focus();
                }}
            >
                <div className="relative border-b border-line p-2">
                    <Search
                        aria-hidden="true"
                        strokeWidth={1.75}
                        className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-ink-faint"
                    />
                    <Input
                        ref={searchRef}
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setActive(0);
                        }}
                        onKeyDown={onSearchKeyDown}
                        placeholder={searchPlaceholder}
                        autoComplete="off"
                        aria-label={searchPlaceholder}
                        aria-autocomplete="list"
                        aria-controls={listId}
                        aria-activedescendant={
                            filtered[activeIndex] && listId ? `${listId}-${filtered[activeIndex].value}` : undefined
                        }
                        className="h-8 pl-8"
                    />
                </div>
                <div
                    id={listId}
                    role="listbox"
                    aria-label={aria['aria-label'] ?? placeholder}
                    className="max-h-60 overflow-y-auto p-1"
                >
                    {filtered.length === 0 ? (
                        <p className="px-2 py-3 text-center text-sm text-ink-subtle">{emptyLabel}</p>
                    ) : (
                        filtered.map((item, index) => {
                            const isActive = index === activeIndex;
                            const isSelected = item.value === value;

                            return (
                                <button
                                    key={item.value}
                                    type="button"
                                    id={listId ? `${listId}-${item.value}` : undefined}
                                    role="option"
                                    aria-selected={isSelected}
                                    onMouseEnter={() => setActive(index)}
                                    onClick={() => choose(item.value)}
                                    className={cn(
                                        'flex w-full cursor-default items-center gap-2 rounded-[var(--radius-xs)] py-1.5 pr-2 pl-2 text-left text-sm outline-none',
                                        isActive ? 'bg-canvas-sunk text-ink' : 'text-ink-muted',
                                        isSelected && 'font-medium text-ink',
                                    )}
                                >
                                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                                    {item.keywords ? (
                                        <span className="shrink-0 font-mono text-xs text-ink-faint">{item.keywords}</span>
                                    ) : null}
                                    {isSelected ? (
                                        <Check aria-hidden="true" strokeWidth={2} className="size-3.5 shrink-0 text-accent" />
                                    ) : (
                                        <span aria-hidden="true" className="size-3.5 shrink-0" />
                                    )}
                                </button>
                            );
                        })
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}

export {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectScrollDownButton,
    SelectScrollUpButton,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
};
