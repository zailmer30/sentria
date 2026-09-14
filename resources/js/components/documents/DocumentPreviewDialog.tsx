import { DocumentPdfViewer } from '@/components/documents/DocumentPdfViewer';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useTranslations } from '@/lib/i18n';

type DocumentPreviewDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    src: string | null;
};

export function DocumentPreviewDialog({ open, onOpenChange, title, src }: DocumentPreviewDialogProps) {
    const { t } = useTranslations();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                title={title}
                description={t('sessions.view_document_hint')}
                className="h-[min(90vh,52rem)] max-w-5xl overflow-hidden"
                bodyClassName="flex min-h-0 flex-1 flex-col p-0"
            >
                {open && src ? (
                    <DocumentPdfViewer key={src} src={src} className="h-full min-h-0 rounded-none border-0" presentation />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
