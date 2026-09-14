import { Button } from '@/components/ui/button';
import { Definition, DefinitionList } from '@/components/ui/definition-list';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { StatusChip, toneForState } from '@/components/ui/status';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';

export type ReportDetail = {
    id: string;
    report_number: string | null;
    recommendation: string;
    status: string;
    findings?: string | null;
    recommendation_notes?: string | null;
    submitted_at: string | null;
    submitter?: string | null;
};

type Props = {
    report: ReportDetail | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function slugKey(value: string): string {
    return value.replaceAll('-', '_');
}

export function ViewReportDialog({ report, open, onOpenChange }: Props) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();

    if (!report) {
        return null;
    }

    const title = report.report_number ?? t('committees.draft_report');
    const findings = report.findings?.trim() || null;
    const notes = report.recommendation_notes?.trim() || null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={title} description={t('documents.view_report_hint')}>
                <div className="space-y-4">
                    <DefinitionList className="border-y-0">
                        <Definition label={t('committees.status')}>
                            <StatusChip tone={toneForState(report.status)} size="sm">
                                {t(`committees.report_status_${slugKey(report.status)}`)}
                            </StatusChip>
                        </Definition>
                        <Definition label={t('committees.recommendation')}>
                            {t(`committees.recommendation_${slugKey(report.recommendation)}`)}
                        </Definition>
                        {report.submitter ? (
                            <Definition label={t('committees.submitted_by')}>{report.submitter}</Definition>
                        ) : null}
                        <Definition label={t('committees.submitted_at')} numeric>
                            {formatDate(report.submitted_at)}
                        </Definition>
                    </DefinitionList>

                    <div>
                        <h4 className="label-eyebrow">{t('committees.findings')}</h4>
                        {findings ? (
                            <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-ink">{findings}</p>
                        ) : (
                            <p className="mt-1 text-sm text-ink-muted">{t('documents.view_report_empty_findings')}</p>
                        )}
                    </div>

                    <div>
                        <h4 className="label-eyebrow">{t('committees.recommendation_notes')}</h4>
                        {notes ? (
                            <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-ink">{notes}</p>
                        ) : (
                            <p className="mt-1 text-sm text-ink-muted">{t('documents.view_report_empty_notes')}</p>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                        {t('actions.close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
