import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

/**
 * The window a surface is reporting on. Changing it changes what the figures
 * count, never what they mean, so it is a segmented control rather than a
 * filter — the choice is always visible and always one of a small fixed set.
 */

export type RangeOption = {
    /** Sent to the server, e.g. `7d`. */
    value: string;
    /** Short visible label. */
    label: string;
    /** Spoken label, e.g. "Last 7 days". */
    description: string;
};

type RangeToggleProps = {
    options: RangeOption[];
    value: string;
    onChange: (value: string) => void;
    /** Accessible name for the group, e.g. "Reporting period". */
    label: string;
    size?: 'sm' | 'default';
    className?: string;
};

export function RangeToggle({
    options,
    value,
    onChange,
    label,
    size = 'sm',
    className,
}: RangeToggleProps) {
    return (
        <ToggleGroup
            type="single"
            value={value}
            size={size}
            aria-label={label}
            // Radix clears the value when the active item is pressed again; a
            // reporting period is never "none", so an empty change is ignored.
            onValueChange={(next) => {
                if (next) {
                    onChange(next);
                }
            }}
            className={cn('shrink-0', className)}
        >
            {options.map((option) => (
                <ToggleGroupItem
                    key={option.value}
                    value={option.value}
                    aria-label={option.description}
                    title={option.description}
                >
                    {option.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
