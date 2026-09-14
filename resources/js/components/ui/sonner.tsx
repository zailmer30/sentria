import { useTheme } from '@/hooks/useTheme';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/**
 * Transient confirmations only. Anything that has to stay on the page until it
 * is dealt with — a validation failure, a restricted-access warning — belongs in
 * a `Notice`, where it cannot time out on someone who looked away.
 */
export function Toaster(props: ToasterProps) {
    const { theme } = useTheme();

    return (
        <Sonner
            theme={theme}
            position="bottom-right"
            className="toaster"
            toastOptions={{
                classNames: {
                    toast: 'group rounded-[var(--radius-md)]! border! border-line! bg-surface-raised! text-ink! shadow-[var(--shadow-lg)]!',
                    description: 'text-ink-muted!',
                    actionButton: 'bg-accent! text-[var(--color-accent-on)]!',
                    cancelButton: 'bg-canvas-sunk! text-ink-muted!',
                    error: 'text-critical!',
                    success: 'text-success!',
                },
            }}
            {...props}
        />
    );
}
