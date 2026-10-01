import { CommitteeReportBody } from '@/components/documents/CommitteeReportBody';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ReadingPackItem } from '@/pages/Sessions/Floor/shared';
import { FileWarning, ScrollText } from 'lucide-react';
import { memo } from 'react';

/**
 * The committee report, put on the wall. Same takeover as a projected
 * document: the title plate shrinks, and the findings fill the board so the
 * chamber is looking at one thing.
 */

type ChamberReportProps = {
    item: ReadingPackItem | null;
    className?: string;
};

export const ChamberReport = memo(function ChamberReport({ item, className }: ChamberReportProps) {
    const { t } = useTranslations();
    const report = item?.committee_report ?? null;
    const title = report?.report_number ?? t('sessions.committee_hour.title');

    return (
        <section className={cn('flex min-h-0 min-w-0 flex-col bg-canvas-sunk', className)}>
            <header className="flex shrink-0 items-center gap-3 border-b border-line bg-surface px-4 py-2.5 md:px-5">
                <ScrollText aria-hidden="true" strokeWidth={1.75} className="size-4 shrink-0 text-ink-subtle" />
                <div className="min-w-0 flex-1">
                    <p className="label-eyebrow">{t('sessions.hall.on_screen')}</p>
                    <p className="truncate text-[clamp(0.9375rem,1.15vw,1.375rem)] font-semibold tracking-[-0.01em] text-ink">
                        {report ? title : t('sessions.hall.no_report_short')}
                    </p>
                </div>
            </header>

            <div className="min-h-0 flex-1 overflow-y-auto bg-surface px-5 py-6 md:px-8 md:py-8">
                {report ? (
                    <CommitteeReportBody report={report} display surface="canvas" />
                ) : (
                    <div className="flex h-full flex-col items-center justify-center gap-4 px-8 text-center">
                        <FileWarning
                            aria-hidden="true"
                            strokeWidth={1.25}
                            className="size-[clamp(2rem,3.5vw,3.5rem)] text-ink-faint"
                        />
                        <p className="max-w-3xl text-[clamp(1.125rem,1.9vw,2rem)] font-semibold tracking-[-0.015em] text-ink">
                            {t('sessions.hall.no_report_short')}
                        </p>
                        <p className="max-w-2xl text-[clamp(0.875rem,1.1vw,1.25rem)] leading-relaxed text-ink-muted">
                            {t('sessions.hall.report_unavailable')}
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
});
