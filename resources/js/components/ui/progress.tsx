import { cn } from '@/lib/utils';
import * as ProgressPrimitive from '@radix-ui/react-progress';
import * as React from 'react';

/**
 * A quantity against a ceiling: minutes through their pipeline, referrals
 * closed against referrals opened. The track is the recessed tone and the
 * indicator inherits `currentColor`, so a caller states the meaning by setting
 * a text colour rather than by picking a bar colour.
 */
function Progress({
    className,
    value,
    ...props
}: React.ComponentProps<typeof ProgressPrimitive.Root>) {
    return (
        <ProgressPrimitive.Root
            data-slot="progress"
            className={cn(
                'relative h-1.5 w-full overflow-hidden rounded-full bg-[var(--color-chart-track)] text-accent',
                className,
            )}
            {...props}
        >
            <ProgressPrimitive.Indicator
                data-slot="progress-indicator"
                className="h-full w-full flex-1 rounded-full bg-current transition-transform duration-[var(--duration-slow)] ease-[var(--ease-out-quint)]"
                style={{ transform: `translateX(-${100 - (value ?? 0)}%)` }}
            />
        </ProgressPrimitive.Root>
    );
}

export { Progress };
