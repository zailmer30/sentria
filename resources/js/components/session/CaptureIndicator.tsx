import { Button } from '@/components/ui/button';
import { LiveDot } from '@/components/ui/status';
import { useCaptureLevel, useCaptureStatus } from '@/hooks/useChamberCapture';
import { chamberCapture } from '@/lib/chamberCapture';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Square } from 'lucide-react';

/**
 * Recording now outlives the page that started it, so it has to be visible
 * from wherever the clerk happens to be. Without this the only evidence a
 * sitting is being recorded would be the tab the operator navigated away from.
 */
export function CaptureIndicator({ className }: { className?: string }) {
    const { t } = useTranslations();
    const { phase, sessionId, sessionLabel, state, stats } = useCaptureStatus();
    const level = useCaptureLevel(phase === 'listening');

    if (phase === 'stopped' || sessionId === null) {
        return null;
    }

    const recording = state?.capture === 'record';
    const meter = Math.min(1, level / 0.036);

    return (
        <div
            role="status"
            className={cn(
                'flex flex-wrap items-center gap-x-3 gap-y-2 rounded-[var(--radius-md)] border px-3 py-2',
                recording
                    ? 'border-[var(--color-live-line)] bg-live-soft text-[var(--color-live-ink)]'
                    : 'border-line bg-canvas-sunk text-ink',
                className,
            )}
        >
            <span className="flex items-center gap-1.5 text-sm font-semibold">
                {recording ? <LiveDot className="size-1.5" /> : null}
                {recording ? t('capture.indicator.recording') : t('capture.indicator.listening')}
            </span>

            <span className="h-1.5 w-16 overflow-hidden rounded-full bg-canvas-sunk" aria-hidden="true">
                <span
                    className={cn(
                        'block h-full rounded-full transition-[width] duration-75',
                        recording ? 'bg-live' : 'bg-accent',
                    )}
                    style={{ width: `${Math.round(meter * 100)}%` }}
                />
            </span>

            {sessionLabel ? <span className="truncate text-sm text-ink-muted">{sessionLabel}</span> : null}

            {stats.queued > 0 ? (
                <span className="font-mono text-xs text-ink-muted">{t('capture.indicator.queued', { count: stats.queued })}</span>
            ) : null}

            <div className="ml-auto flex shrink-0 items-center gap-2">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/sessions/${sessionId}/capture`}>{t('capture.indicator.open')}</Link>
                </Button>
                <Button type="button" variant="secondary" size="sm" onClick={() => chamberCapture.stop()}>
                    <Square aria-hidden="true" strokeWidth={1.75} />
                    {t('capture.stop')}
                </Button>
            </div>
        </div>
    );
}
