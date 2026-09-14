import { DocumentPdfViewer } from '@/components/documents/DocumentPdfViewer';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PrivateNote = {
    id: string;
    body: string;
};

type Props = {
    document: {
        id: string;
        slug: string;
        title: string;
        document_type_label: string;
    };
    version: {
        id: string;
        version_number: number;
        original_filename: string;
        mime_type: string;
    };
    privateNotes: PrivateNote[];
    annotations: unknown[];
};

export default function DocumentsView({ document, version, privateNotes, annotations = [] }: Props) {
    const { t } = useTranslations();
    const isPdf = version.mime_type === 'application/pdf';
    const previewUrl = `/documents/${document.slug}/versions/${version.id}/preview`;
    const annotationsUrl = `/documents/${document.slug}/versions/${version.id}/annotations`;
    const noteForm = useForm({
        notable_type: 'App\\Models\\Document',
        notable_id: document.id,
        body: '',
    });

    function saveNote(event: FormEvent) {
        event.preventDefault();
        noteForm.post('/private-notes', { preserveScroll: true, onSuccess: () => noteForm.setData('body', '') });
    }

    return (
        <AppLayout title={document.title} wide>
            <div className="flex min-h-[calc(100dvh-8rem)] flex-col gap-4">
                <PageHeader
                    title={document.title}
                    description={`${document.document_type_label} · ${t('documents.version_label', { number: version.version_number })} · ${version.original_filename}`}
                    actions={
                        <>
                            <Button variant="secondary" asChild>
                                <Link href={`/documents/${document.slug}`}>{t('documents.back_to_document')}</Link>
                            </Button>
                            <Button variant="secondary" asChild>
                                <a href={`/documents/${document.slug}/versions/${version.id}/download`}>
                                    {t('documents.download_source')}
                                </a>
                            </Button>
                        </>
                    }
                />

                <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="min-h-[70vh] overflow-hidden rounded-[var(--radius-md)] border border-line bg-surface">
                        {isPdf ? (
                            <DocumentPdfViewer
                                src={previewUrl}
                                className="h-full min-h-[70vh] rounded-none border-0"
                                annotations={annotations}
                                saveUrl={annotationsUrl}
                            />
                        ) : (
                            <p className="p-5 text-sm text-ink-muted">{t('documents.preview_pdf_only')}</p>
                        )}
                    </div>

                    <Panel as="aside">
                        <PanelHead>
                            <PanelTitle>{t('sessions.my_notes')}</PanelTitle>
                        </PanelHead>
                        <PanelBody>
                            {privateNotes.length > 0 ? (
                                <ul className="space-y-2">
                                    {privateNotes.map((note) => (
                                        <li
                                            key={note.id}
                                            className="rounded-[var(--radius-sm)] bg-canvas-sunk px-3 py-2 text-sm text-ink"
                                        >
                                            {note.body}
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                            <form onSubmit={saveNote} className={privateNotes.length > 0 ? 'mt-4 space-y-3' : 'space-y-3'}>
                                <Textarea
                                    value={noteForm.data.body}
                                    onChange={(event) => noteForm.setData('body', event.target.value)}
                                    rows={6}
                                    placeholder={t('sessions.note_placeholder')}
                                    required
                                />
                                <Button type="submit" variant="secondary" size="sm" disabled={noteForm.processing}>
                                    {t('sessions.save_note')}
                                </Button>
                            </form>
                        </PanelBody>
                    </Panel>
                </div>
            </div>
        </AppLayout>
    );
}
