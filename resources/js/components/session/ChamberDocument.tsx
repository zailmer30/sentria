import { DocumentPdfViewer, type HallDocumentView } from '@/components/documents/DocumentPdfViewer';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ReadingPackItem } from '@/pages/Sessions/Floor/shared';
import { FileText, FileWarning } from 'lucide-react';
import { memo } from 'react';

/**
 * The paper, put on the wall. This is the same file the members have on their
 * benches, rendered read-only for the room: no markup tools, no private
 * annotations, no account of who is reading it.
 *
 * It carries its own title strip because a projected page usually opens
 * mid-document, and the gallery needs to know what they are looking at without
 * waiting for someone to scroll back to the cover.
 *
 * Memoised: the board around it re-renders every second to keep the sitting
 * clock moving, and rebuilding a PDF surface at that rate would be ruinous.
 */

type ChamberDocumentProps = {
    item: ReadingPackItem | null;
    className?: string;
    /** Live viewport driven by the secretariat console. */
    view?: HallDocumentView | null;
};

export const ChamberDocument = memo(function ChamberDocument({ item, className, view = null }: ChamberDocumentProps) {
    const { t } = useTranslations();
    const document = item?.document ?? null;
    const canPreview = Boolean(document?.can_preview && document.preview_url);

    return (
        <section className={cn('flex min-h-0 min-w-0 flex-col bg-canvas-sunk', className)}>
            <header className="flex shrink-0 items-center gap-3 border-b border-line bg-surface px-4 py-2.5 md:px-5">
                <FileText aria-hidden="true" strokeWidth={1.75} className="size-4 shrink-0 text-ink-subtle" />
                <div className="min-w-0 flex-1">
                    <p className="label-eyebrow">{t('sessions.hall.on_screen')}</p>
                    <p className="truncate text-[clamp(0.9375rem,1.15vw,1.375rem)] font-semibold tracking-[-0.01em] text-ink">
                        {document ? document.title : t('sessions.hall.no_document_short')}
                    </p>
                </div>
            </header>

            <div className="min-h-0 flex-1 overflow-hidden">
                {!document ? (
                    <ChamberDocumentNotice
                        title={t('sessions.hall.no_document_short')}
                        detail={t('sessions.reading.no_document')}
                    />
                ) : !canPreview ? (
                    <ChamberDocumentNotice
                        title={document.title}
                        detail={
                            document.mime_type && document.mime_type !== 'application/pdf'
                                ? t('documents.preview_pdf_only')
                                : t('sessions.hall.document_unavailable')
                        }
                    />
                ) : (
                    <DocumentPdfViewer
                        key={document.version_id ?? document.preview_url!}
                        src={document.preview_url!}
                        presentation
                        syncRole="follower"
                        view={view}
                        className="h-full min-h-0 rounded-none border-0"
                    />
                )}
            </div>
        </section>
    );
});

function ChamberDocumentNotice({ title, detail }: { title: string; detail: string }) {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-4 px-8 text-center">
            <FileWarning aria-hidden="true" strokeWidth={1.25} className="size-[clamp(2rem,3.5vw,3.5rem)] text-ink-faint" />
            <p className="max-w-3xl text-[clamp(1.125rem,1.9vw,2rem)] font-semibold tracking-[-0.015em] text-ink">{title}</p>
            <p className="max-w-2xl text-[clamp(0.875rem,1.1vw,1.25rem)] leading-relaxed text-ink-muted">{detail}</p>
        </div>
    );
}
