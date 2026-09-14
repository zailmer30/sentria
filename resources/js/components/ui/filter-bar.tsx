import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Search } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

/**
 * The finding aid above a register. On an index page it sits inside the same
 * card as the rows (`inline`); elsewhere it can still stand as its own frame.
 */

type FilterBarProps = {
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    children: ReactNode;
    /** Rendered after the submit control: a reset link, a count, a toggle. */
    trailing?: ReactNode;
    className?: string;
    submitLabel?: string;
    /** Hide the dedicated submit control; Enter on the search field still submits. */
    hideSubmit?: boolean;
    /** Sits inside `RegisterFrame` instead of carrying its own card. */
    inline?: boolean;
};

export function FilterBar({
    onSubmit,
    children,
    trailing,
    className,
    submitLabel,
    hideSubmit = false,
    inline = false,
}: FilterBarProps) {
    const { t } = useTranslations();

    return (
        <form
            onSubmit={onSubmit}
            className={cn(
                'flex flex-wrap items-end gap-x-3 gap-y-3',
                inline
                    ? 'border-b border-line px-5 py-4'
                    : 'rounded-[var(--radius-lg)] border border-line bg-surface px-4 py-4 shadow-[var(--shadow-xs)]',
                className,
            )}
        >
            {children}

            {hideSubmit ? null : (
                <Button type="submit" variant="secondary" size="default">
                    <Search aria-hidden="true" strokeWidth={2} />
                    {submitLabel ?? t('actions.filter')}
                </Button>
            )}

            {trailing}
        </form>
    );
}

/** A single filter cell. `grow` for the keyword field, fixed for selects. */
export function FilterCell({
    children,
    grow = false,
    className,
}: {
    children: ReactNode;
    grow?: boolean;
    className?: string;
}) {
    return <div className={cn('flex flex-col gap-1.5', grow && 'min-w-48 flex-1', className)}>{children}</div>;
}

/** Keyword field with a leading magnifying glass, matching the documents register. */
export function SearchField({
    id,
    value,
    onChange,
    placeholder,
    label,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    label: string;
}) {
    return (
        <div className="relative">
            <Search
                aria-hidden="true"
                strokeWidth={1.75}
                className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-ink-faint"
            />
            <Input
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className="pl-8"
                aria-label={label}
            />
        </div>
    );
}
