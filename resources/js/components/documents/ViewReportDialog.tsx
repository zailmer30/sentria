import { CommitteeReportBody, type CommitteeReportDetail } from '@/components/documents/CommitteeReportBody';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useTranslations } from '@/lib/i18n';

export type ReportDetail = CommitteeReportDetail;

type Props = {
    report: ReportDetail | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ViewReportDialog({ report, open, onOpenChange }: Props) {
    const { t } = useTranslations();

    if (!report) {
        return null;
    }

    const title = report.report_number ?? t('committees.draft_report');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                title={title}
                description={t('documents.view_report_hint')}
                titleClassName="font-mono text-base tracking-tight"
                className="max-h-[min(40rem,calc(100vh-2rem))] max-w-xl"
                bodyClassName="max-h-[min(28rem,calc(100vh-8rem))] flex-none overflow-y-auto px-0 py-0"
                footer={
                    <div className="flex shrink-0 items-center justify-end border-t border-line px-5 py-3">
                        <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                            {t('actions.close')}
                        </Button>
                    </div>
                }
            >
                <CommitteeReportBody report={report} flush />
            </DialogContent>
        </Dialog>
    );
}
