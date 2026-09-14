import { Gauge } from '@/components/ui/gauge';
import { StatusChip } from '@/components/ui/status';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Quorum, countable at a glance from across the chamber: one seat marker per
 * seated member, filled when present, with a gap marking the threshold so the
 * presiding officer reads the shortfall without doing arithmetic.
 *
 * On a floor surface an arc is drawn beside the markers for the read from the
 * back of the room. It is an addition, never a replacement — the count and the
 * markers are the record, and both stand on their own if the arc never draws.
 *
 * The system reports; the presiding officer decides. This never says "proceed".
 */

type QuorumMeterProps = {
    presentCount: number;
    seatedCount: number;
    required: number;
    met: boolean;
    className?: string;
    /** Larger markers and the arc, for the rostrum and chamber tablets. */
    floor?: boolean;
    /** Hide the seat-marker strip (dashboard cards that only need the ring). */
    markers?: boolean;
};

export function QuorumMeter({
    presentCount,
    seatedCount,
    required,
    met,
    className,
    floor = false,
    markers = true,
}: QuorumMeterProps) {
    const { t } = useTranslations();
    // A seated roster of unknown size still renders the counted facts.
    const seats = Math.max(seatedCount, presentCount, required);
    const ringMax = Math.max(seatedCount, required, 1);

    const counts = (
        <div className={cn('flex flex-col gap-2', floor && !markers ? '' : !floor ? className : '')}>
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                <span className={cn('font-mono font-medium tracking-[-0.02em] text-ink', floor ? 'text-figure' : 'text-lg')}>
                    {presentCount}
                    <span className="text-ink-faint">/{required}</span>
                </span>
                <StatusChip tone={met ? 'final' : 'live'} size="sm">
                    {met ? t('sessions.quorum_met') : t('sessions.quorum_not_met')}
                </StatusChip>
            </div>

            {markers ? (
                <div
                    className="flex flex-wrap items-center gap-0.5"
                    role="img"
                    aria-label={t('sessions.quorum_meter_label', {
                        present: presentCount,
                        required,
                        seated: seats,
                    })}
                >
                    {Array.from({ length: seats }, (_, index) => {
                        const present = index < presentCount;
                        const atThreshold = index === required - 1;

                        return (
                            <span
                                key={index}
                                aria-hidden="true"
                                className={cn(
                                    floor ? 'h-4 w-2' : 'h-3 w-1.5',
                                    'rounded-xs',
                                    present ? 'bg-ink' : 'bg-line-strong',
                                    // The gap and tick marking where quorum is reached.
                                    atThreshold && 'mr-2.5 outline-1 outline-offset-2 outline-line-control',
                                )}
                            />
                        );
                    })}
                </div>
            ) : null}
        </div>
    );

    if (!floor) {
        return counts;
    }

    return (
        <div className={cn('flex flex-wrap items-center gap-4', className)}>
            <Gauge
                size={104}
                value={presentCount}
                max={ringMax}
                threshold={required}
                tone={met ? 'success' : 'live'}
                label={t('sessions.quorum')}
            >
                <span className="font-mono text-figure-sm font-medium tracking-[-0.02em] text-ink">
                    {presentCount}
                </span>
                <span className="font-mono text-2xs text-ink-faint">
                    {t('sessions.quorum_of', { required: seatedCount || required })}
                </span>
            </Gauge>
            {markers ? counts : null}
        </div>
    );
}
