import { cn } from '@/lib/utils';
import * as LabelPrimitive from '@radix-ui/react-label';
import * as React from 'react';

/**
 * Form labels are read, not scanned, so they stay sentence case at a legible
 * size. The uppercase micro-label (`.label-eyebrow`) is for column heads and
 * provenance, where the label is furniture rather than instruction.
 */
export const Label = React.forwardRef<
    React.ElementRef<typeof LabelPrimitive.Root>,
    React.ComponentPropsWithoutRef<typeof LabelPrimitive.Root>
>(({ className, ...props }, ref) => (
    <LabelPrimitive.Root ref={ref} className={cn('block text-xs font-medium text-ink', className)} {...props} />
));
Label.displayName = LabelPrimitive.Root.displayName;
