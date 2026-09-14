import type { PageProps } from '@/types';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

/**
 * Errors are held four times as long as confirmations and carry a close button,
 * because a failure that times out unread is a failure that did not happen as
 * far as the user is concerned.
 */
export function notifyError(message: string): void {
    toast.error(message, {
        icon: <XCircle aria-hidden="true" strokeWidth={2} className="size-4" />,
        duration: 16000,
        closeButton: true,
    });
}

export function notifySuccess(message: string): void {
    toast.success(message, {
        icon: <CheckCircle2 aria-hidden="true" strokeWidth={2} className="size-4" />,
    });
}

/**
 * Every controller in this application sets a flash message — "Session
 * adjourned.", "Ballot recorded.", "Publication workflow updated." — and it is
 * the receipt for a state change the user just caused.
 *
 * A receipt is transient by nature, so it is raised as a toast rather than
 * pushed into the page, where it would shift the layout under someone
 * mid-read. Anything that must persist until it is dealt with is a `Notice`.
 *
 * Renders nothing: `Toaster` owns the surface, this owns the trigger.
 */
export function FlashRegion(_props: { className?: string }) {
    const { flash } = usePage<PageProps>().props;
    const { t } = useTranslations();
    const announced = useRef<string | null>(null);

    const success = flash?.success ?? null;
    const error = flash?.error ?? null;

    useEffect(() => {
        const message = success ?? error;

        if (!message) {
            announced.current = null;

            return;
        }

        // Inertia re-shares flash props on partial reloads; a message is a
        // receipt for one event and must be announced exactly once.
        const signature = `${success ? 'success' : 'error'}:${message}`;

        if (announced.current === signature) {
            return;
        }

        announced.current = signature;

        if (success) {
            notifySuccess(t(success));

            return;
        }

        if (error) {
            notifyError(t(error));
        }
    }, [success, error]);

    return null;
}
