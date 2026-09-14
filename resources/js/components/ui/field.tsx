import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { AlertCircle } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * One wrapper for every form control, so a label, a hint and an error message
 * are never optional by accident. Errors name the problem next to the field
 * that caused it.
 */

type FieldProps = {
    id: string;
    label: string;
    children: ReactNode;
    /** Guidance shown before the user makes a mistake. */
    hint?: string;
    error?: string;
    required?: boolean;
    className?: string;
};

export function Field({ id, label, children, hint, error, required = false, className }: FieldProps) {
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <Label htmlFor={id}>
                {label}
                {required ? (
                    <span aria-hidden="true" className="ml-1 text-critical">
                        *
                    </span>
                ) : null}
            </Label>

            {children}

            {error ? (
                <p id={errorId} className="flex items-start gap-1.5 text-xs font-medium text-critical">
                    <AlertCircle aria-hidden="true" className="mt-px size-3.5 shrink-0" strokeWidth={2} />
                    <span>{error}</span>
                </p>
            ) : hint ? (
                <p id={hintId} className="text-xs text-ink-subtle">
                    {hint}
                </p>
            ) : null}
        </div>
    );
}

/** Ties a control to its own hint and error nodes. Spread onto the input. */
export function fieldAria(id: string, { hint, error }: { hint?: string; error?: string }) {
    const describedBy = [hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(' ');

    return {
        id,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': describedBy || undefined,
    } as const;
}
