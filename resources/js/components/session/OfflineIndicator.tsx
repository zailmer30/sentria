import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { CloudUpload, WifiOff } from 'lucide-react';

/**
 * Connectivity on the floor is a fact a member must be able to trust, so it is
 * stated plainly rather than implied by a small icon. Queued ballots are
 * counted, because "did my vote count" is the only question that matters here —
 * which is also why losing the connection earns the national red.
 */

type OfflineIndicatorProps = {
    online: boolean;
    pendingVotes?: number;
    flushing?: boolean;
    className?: string;
};

export function OfflineIndicator({ online, pendingVotes = 0, flushing = false, className }: OfflineIndicatorProps) {
    const { t } = useTranslations();

    if (online && pendingVotes === 0 && !flushing) {
        return null;
    }

    const offline = !online;
    const Icon = offline ? WifiOff : CloudUpload;

    return (
        <div
            role="status"
            aria-live="polite"
            className={cn(
                'flex items-start gap-2.5 rounded-[var(--radius-md)] border px-3 py-2.5',
                offline
                    ? 'border-[var(--color-live-line)] bg-live-soft text-[var(--color-live-ink)]'
                    : 'border-[var(--color-warning-line)] bg-warning-soft text-warning',
                className,
            )}
        >
            <Icon aria-hidden="true" strokeWidth={2} className="mt-px size-4 shrink-0" />
            <div className="flex-1 text-sm font-medium">
                {offline
                    ? t('sessions.offline_mode')
                    : flushing
                      ? t('sessions.syncing_votes')
                      : t('sessions.votes_pending_sync', { count: pendingVotes })}
                {offline && pendingVotes > 0 ? (
                    <p className="mt-0.5 font-normal">{t('sessions.votes_pending_sync', { count: pendingVotes })}</p>
                ) : null}
            </div>
        </div>
    );
}
