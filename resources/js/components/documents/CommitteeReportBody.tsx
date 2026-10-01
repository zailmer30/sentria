import { StatusChip, toneForState } from '@/components/ui/status';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Check, Clock, Minus, PenLine, X, type LucideIcon } from 'lucide-react';

export type CommitteeReportDetail = {
    id: string;
    report_number: string | null;
    recommendation: string;
    status: string;
    findings?: string | null;
    recommendation_notes?: string | null;
    submitted_at: string | null;
    submitter?: string | null;
};

export function reportSlugKey(value: string): string {
    return value.replaceAll('-', '_');
}

type RecommendationTone = {
    icon: LucideIcon;
    surface: string;
    border: string;
    ink: string;
    mark: string;
};

const RECOMMENDATION_TONE: Record<string, RecommendationTone> = {
    approve: {
        icon: Check,
        surface: 'bg-success-soft',
        border: 'border-success-line',
        ink: 'text-success',
        mark: 'border-success-line bg-surface text-success',
    },
    disapprove: {
        icon: X,
        surface: 'bg-critical-soft',
        border: 'border-critical-line',
        ink: 'text-critical',
        mark: 'border-critical-line bg-surface text-critical',
    },
    amend: {
        icon: PenLine,
        surface: 'bg-info-soft',
        border: 'border-info-line',
        ink: 'text-info',
        mark: 'border-info-line bg-surface text-info',
    },
    defer: {
        icon: Clock,
        surface: 'bg-warning-soft',
        border: 'border-warning-line',
        ink: 'text-warning',
        mark: 'border-warning-line bg-surface text-warning',
    },
    'no-action': {
        icon: Minus,
        surface: 'bg-canvas-sunk',
        border: 'border-line',
        ink: 'text-ink',
        mark: 'border-line-strong bg-surface text-ink-muted',
    },
};

const NEUTRAL_TONE: RecommendationTone = {
    icon: Minus,
    surface: 'bg-canvas-sunk',
    border: 'border-line',
    ink: 'text-ink',
    mark: 'border-line-strong bg-surface text-ink-muted',
};

type Props = {
    report: CommitteeReportDetail;
    /** Hall-board type: larger, chamber-readable, no status chip. */
    display?: boolean;
    /** `plate` is white ink on the navy hall plate. `canvas` is the projected report panel. */
    surface?: 'plate' | 'canvas';
    /** Edge-to-edge sections for a dialog that is already the frame. */
    flush?: boolean;
    className?: string;
};

export function CommitteeReportBody({ report, display = false, surface = 'plate', flush = false, className }: Props) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();
    const findings = report.findings?.trim() || null;
    const notes = report.recommendation_notes?.trim() || null;
    const recommendation = t(`committees.recommendation_${reportSlugKey(report.recommendation)}`);
    const ink = surface === 'canvas' ? 'text-ink' : 'text-floor-ink';
    const muted = surface === 'canvas' ? 'text-ink-muted' : 'text-floor-ink-muted';
    const faint = surface === 'canvas' ? 'text-ink-faint' : 'text-floor-ink-faint';

    if (display) {
        return (
            <div className={cn('mx-auto w-full max-w-[68ch] space-y-6 text-left', className)}>
                <p className={cn('text-[clamp(1.125rem,1.8vw,2rem)] leading-snug', ink)}>
                    <span className={faint}>{t('committees.recommendation')}: </span>
                    {recommendation}
                </p>
                {findings ? (
                    <div>
                        <h3 className={cn('text-eyebrow', faint)}>{t('committees.findings')}</h3>
                        <p className={cn('mt-2 whitespace-pre-wrap text-[clamp(1rem,1.5vw,1.75rem)] leading-relaxed', ink)}>
                            {findings}
                        </p>
                    </div>
                ) : null}
                {notes ? (
                    <div>
                        <h3 className={cn('text-eyebrow', faint)}>{t('committees.recommendation_notes')}</h3>
                        <p className={cn('mt-2 whitespace-pre-wrap text-[clamp(1rem,1.5vw,1.75rem)] leading-relaxed', muted)}>
                            {notes}
                        </p>
                    </div>
                ) : null}
            </div>
        );
    }

    const tone = RECOMMENDATION_TONE[report.recommendation] ?? NEUTRAL_TONE;
    const ToneIcon = tone.icon;

    return (
        <div
            className={cn(
                'overflow-hidden bg-surface',
                flush ? 'rounded-none border-0' : 'rounded-[var(--radius-lg)] border border-line',
                className,
            )}
        >
            <div className={cn('flex items-center justify-between gap-4 border-b px-5 py-4', tone.surface, tone.border)}>
                <div className="flex min-w-0 items-center gap-3">
                    <span
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-md)] border',
                            tone.mark,
                        )}
                    >
                        <ToneIcon aria-hidden="true" className="size-4" strokeWidth={1.75} />
                    </span>
                    <div className="min-w-0">
                        <p className="label-eyebrow">{t('committees.recommendation')}</p>
                        <p className={cn('mt-0.5 truncate text-lg font-semibold tracking-tight', tone.ink)}>{recommendation}</p>
                    </div>
                </div>
                <StatusChip tone={toneForState(report.status)} className="shrink-0">
                    {t(`committees.report_status_${reportSlugKey(report.status)}`)}
                </StatusChip>
            </div>

            <dl className="grid grid-cols-1 divide-y divide-line border-b border-line bg-canvas sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                {report.submitter ? (
                    <div className="px-5 py-3">
                        <dt className="label-eyebrow">{t('committees.submitted_by')}</dt>
                        <dd className="mt-1 truncate text-sm font-medium text-ink">{report.submitter}</dd>
                    </div>
                ) : null}
                <div className={cn('px-5 py-3', !report.submitter && 'sm:col-span-2')}>
                    <dt className="label-eyebrow">{t('committees.submitted_at')}</dt>
                    <dd className="mt-1 font-mono text-sm text-ink">{formatDate(report.submitted_at)}</dd>
                </div>
            </dl>

            <div className="space-y-5 px-5 py-5">
                <section>
                    <h4 className="label-eyebrow">{t('committees.findings')}</h4>
                    {findings ? (
                        <p className="mt-2 whitespace-pre-wrap rounded-[var(--radius-md)] bg-canvas px-4 py-3.5 text-sm leading-relaxed text-ink">
                            {findings}
                        </p>
                    ) : (
                        <p className="mt-2 text-sm leading-relaxed text-ink-muted">{t('documents.view_report_empty_findings')}</p>
                    )}
                </section>

                <section>
                    <h4 className="label-eyebrow">{t('committees.recommendation_notes')}</h4>
                    {notes ? (
                        <p className="mt-2 whitespace-pre-wrap rounded-[var(--radius-md)] bg-canvas px-4 py-3.5 text-sm leading-relaxed text-ink">
                            {notes}
                        </p>
                    ) : (
                        <p className="mt-2 text-sm leading-relaxed text-ink-muted">{t('documents.view_report_empty_notes')}</p>
                    )}
                </section>
            </div>
        </div>
    );
}
