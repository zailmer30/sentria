import { DocumentPreviewDialog } from '@/components/documents/DocumentPreviewDialog';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import {
    Register,
    RegisterBody,
    RegisterCell,
    RegisterCellActions,
    RegisterCellPrimary,
    RegisterEmpty,
    RegisterFrame,
    RegisterHead,
    RegisterHeadCell,
    RegisterOpenLink,
    RegisterRow,
} from '@/components/ui/register';
import { StatusChip, toneForState } from '@/components/ui/status';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { Eye, Files } from 'lucide-react';
import { useState } from 'react';

type SessionSummary = {
    id: string;
    session_number: string;
    title: string;
};

type AttachedDocument = {
    id: string;
    slug: string;
    title: string;
    reference_number: string | null;
    document_type_label: string;
    status: string;
    status_label: string;
    can_preview: boolean;
    preview_url: string | null;
    item_number: string | null;
    item_title: string;
};

type Props = {
    session: SessionSummary;
    documents: AttachedDocument[];
};

export default function SessionsDocuments({ session, documents }: Props) {
    const { t } = useTranslations();
    const [preview, setPreview] = useState<AttachedDocument | null>(null);

    return (
        <AppLayout title={t('sessions.attached_documents_title')}>
            <div className="flex flex-col gap-5">
                <PageHeader
                    title={t('sessions.attached_documents_title')}
                    description={t('sessions.attached_documents_subtitle', {
                        session: `${session.session_number} — ${session.title}`,
                    })}
                    actions={
                        <>
                            <Button variant="secondary" asChild>
                                <Link href="/sessions">{t('sessions.back_to_register')}</Link>
                            </Button>
                            <Button variant="secondary" asChild>
                                <Link href={`/sessions/${session.id}`}>{t('sessions.back')}</Link>
                            </Button>
                        </>
                    }
                />

                <RegisterFrame>
                    <Register flush caption={t('sessions.attached_documents_title')} minWidth="52rem">
                        <RegisterHead>
                            <RegisterHeadCell>{t('sessions.agenda_item_number')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('documents.title_label')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('documents.type')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('documents.status')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {documents.length === 0 ? (
                                <RegisterEmpty colSpan={5}>
                                    <EmptyState
                                        bare
                                        icon={Files}
                                        title={t('sessions.attached_documents_empty')}
                                        description={t('sessions.attached_documents_empty_hint')}
                                    />
                                </RegisterEmpty>
                            ) : (
                                documents.map((document) => (
                                    <RegisterRow key={document.id}>
                                        <RegisterCell numeric nowrap>
                                            {document.item_number ?? '—'}
                                        </RegisterCell>
                                        <RegisterCellPrimary
                                            href={`/documents/${document.slug}`}
                                            secondary={
                                                document.reference_number
                                                    ? `${document.reference_number} · ${document.item_title}`
                                                    : document.item_title
                                            }
                                        >
                                            {document.title}
                                        </RegisterCellPrimary>
                                        <RegisterCell nowrap>{document.document_type_label}</RegisterCell>
                                        <RegisterCell nowrap>
                                            <StatusChip tone={toneForState(document.status)} size="sm">
                                                {document.status_label}
                                            </StatusChip>
                                        </RegisterCell>
                                        <RegisterCellActions>
                                            {document.can_preview && document.preview_url ? (
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => setPreview(document)}
                                                >
                                                    <Eye aria-hidden="true" strokeWidth={2} className="size-3.5" />
                                                    {t('documents.view')}
                                                </Button>
                                            ) : null}
                                            <RegisterOpenLink href={`/documents/${document.slug}`}>
                                                {t('register.open')}
                                            </RegisterOpenLink>
                                        </RegisterCellActions>
                                    </RegisterRow>
                                ))
                            )}
                        </RegisterBody>
                    </Register>
                </RegisterFrame>
            </div>

            <DocumentPreviewDialog
                open={preview !== null}
                onOpenChange={(open) => !open && setPreview(null)}
                title={preview?.title ?? t('documents.view')}
                src={preview?.preview_url ?? null}
            />
        </AppLayout>
    );
}
