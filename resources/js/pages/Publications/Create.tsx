import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Field, fieldAria } from '@/components/ui/field';
import { Input, Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { Newspaper } from 'lucide-react';
import { FormEvent } from 'react';

/**
 * A publication record is opened from an existing document. The form cites
 * that source and lets the secretariat set the public title before the
 * workflow starts.
 */

type SourceDocument = {
    id: string;
    slug: string;
    title: string;
    reference_number: string | null;
    abstract: string | null;
};

type Props = {
    documents: SourceDocument[];
};

export default function PublicationsCreate({ documents }: Props) {
    const { t } = useTranslations();
    const initial = documents[0] ?? null;
    const form = useForm({
        document_id: initial?.id ?? '',
        title: initial?.title ?? '',
        summary: initial?.abstract ?? '',
    });

    function selectDocument(id: string) {
        const next = documents.find((document) => document.id === id);

        form.setData({
            document_id: id,
            title: next?.title ?? '',
            summary: next?.abstract ?? '',
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/publications');
    }

    return (
        <AppLayout title={t('publications.start')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader title={t('publications.start')} description={t('publications.create_subtitle')} />

                {documents.length === 0 ? (
                    <EmptyState
                        icon={Newspaper}
                        title={t('publications.no_documents')}
                        description={t('publications.no_documents_hint')}
                        action={
                            <div className="flex flex-wrap items-center justify-center gap-2">
                                <Button variant="primary" asChild>
                                    <Link href="/documents">{t('nav.documents')}</Link>
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href="/publications">{t('publications.cancel')}</Link>
                                </Button>
                            </div>
                        }
                    />
                ) : (
                    <>
                        <Notice tone="info">{t('publications.create_notice')}</Notice>

                        <Panel>
                            <form onSubmit={submit}>
                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('publications.source')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">{t('publications.create_hint')}</p>
                                    </div>

                                    <Field
                                        id="document_id"
                                        label={t('publications.source_document')}
                                        error={form.errors.document_id}
                                        required
                                    >
                                        <SimpleSelect
                                            id="document_id"
                                            value={form.data.document_id}
                                            onValueChange={selectDocument}
                                            items={documents.map((document) => ({
                                                value: document.id,
                                                label: `${document.reference_number ? `${document.reference_number} — ` : ''}${document.title}`,
                                            }))}
                                        />
                                    </Field>

                                    <Field
                                        id="title"
                                        label={t('publications.title_label')}
                                        error={form.errors.title}
                                        required
                                    >
                                        <Input
                                            {...fieldAria('title', { error: form.errors.title })}
                                            value={form.data.title}
                                            onChange={(event) => form.setData('title', event.target.value)}
                                            required
                                            autoComplete="off"
                                        />
                                    </Field>

                                    <Field
                                        id="summary"
                                        label={t('publications.summary')}
                                        error={form.errors.summary}
                                    >
                                        <Textarea
                                            {...fieldAria('summary', { error: form.errors.summary })}
                                            value={form.data.summary}
                                            onChange={(event) => form.setData('summary', event.target.value)}
                                            rows={5}
                                        />
                                    </Field>
                                </PanelSection>

                                <PanelFoot>
                                    <Button type="submit" variant="primary" disabled={form.processing}>
                                        {form.processing ? t('publications.saving') : t('publications.start')}
                                    </Button>
                                    <Button variant="ghost" asChild>
                                        <Link href="/publications">{t('publications.cancel')}</Link>
                                    </Button>
                                </PanelFoot>
                            </form>
                        </Panel>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
