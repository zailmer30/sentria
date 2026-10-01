import { Button } from '@/components/ui/button';
import { ConfidentialityMark } from '@/components/ui/confidentiality';
import { Field, fieldAria } from '@/components/ui/field';
import { Input, Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

/**
 * Corrects how a filed record is cited and withheld. The source file is not
 * replaced here — versions are uploaded on the record itself. Save is the only
 * accent; cancel never competes with it.
 */

type DocumentDetail = {
    slug: string;
    title: string;
    document_type: string;
    confidentiality: string;
    abstract: string | null;
    author: string | null;
    external_author?: string | null;
    committee_id: string | null;
    reference_number: string | null;
    tags: string[];
    enacting_clause?: string | null;
    explanatory_note?: string | null;
};

type Props = {
    document: DocumentDetail;
    documentTypes: { value: string; label: string }[];
    confidentialityLevels: { value: string; label: string }[];
    committees: { id: string; name: string }[];
};

export default function DocumentsEdit({ document, documentTypes, confidentialityLevels, committees }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        title: document.title,
        document_type: document.document_type,
        confidentiality: document.confidentiality,
        abstract: document.abstract ?? '',
        external_author: document.external_author || document.author || '',
        committee_id: document.committee_id ?? '',
        reference_number: document.reference_number ?? '',
        tags: document.tags,
        enacting_clause: document.enacting_clause ?? '',
        explanatory_note: document.explanatory_note ?? '',
    });

    const classificationNotice =
        form.data.confidentiality === 'restricted' || form.data.confidentiality === 'confidential'
            ? t('documents.confidentiality_restricted_notice')
            : null;
    const isMeasure = isLegislativeMeasure(form.data.document_type);
    const isOrdinance = isOrdinanceMeasure(form.data.document_type);

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            abstract: data.abstract || null,
            external_author: data.external_author.trim(),
            committee_id: data.committee_id || null,
            reference_number: data.reference_number || null,
            enacting_clause: data.enacting_clause || null,
            explanatory_note: data.explanatory_note || null,
        }));

        form.put(`/documents/${document.slug}`);
    }

    return (
        <AppLayout title={t('documents.edit')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader title={t('documents.edit')} description={t('documents.edit_subtitle')} />

                <Notice tone="info">{t('documents.edit_notice')}</Notice>

                <Panel>
                    <form onSubmit={submit}>
                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('documents.form_identity')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('documents.form_identity_hint')}</p>
                            </div>

                            <Field
                                id="title"
                                label={t('documents.title_label')}
                                hint={t('documents.title_hint')}
                                error={form.errors.title}
                                required
                            >
                                <Input
                                    {...fieldAria('title', { hint: t('documents.title_hint'), error: form.errors.title })}
                                    value={form.data.title}
                                    onChange={(event) => form.setData('title', event.target.value)}
                                    required
                                    autoComplete="off"
                                    placeholder={t('documents.title_placeholder')}
                                />
                            </Field>

                            <Field
                                id="external_author"
                                label={t('documents.author')}
                                hint={t('documents.author_hint')}
                                error={form.errors.external_author}
                                required
                            >
                                <Input
                                    {...fieldAria('external_author', {
                                        hint: t('documents.author_hint'),
                                        error: form.errors.external_author,
                                    })}
                                    value={form.data.external_author}
                                    onChange={(event) => form.setData('external_author', event.target.value)}
                                    required
                                    autoComplete="off"
                                    placeholder={t('documents.author_placeholder')}
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="document_type"
                                    label={t('documents.type')}
                                    hint={t('documents.type_hint')}
                                    error={form.errors.document_type}
                                    required
                                >
                                    <SimpleSelect
                                        id="document_type"
                                        value={form.data.document_type}
                                        onValueChange={(value) => form.setData('document_type', value)}
                                        aria-invalid={Boolean(form.errors.document_type)}
                                        items={documentTypes}
                                    />
                                </Field>
                                <Field
                                    id="reference_number"
                                    label={t('documents.reference')}
                                    hint={t('documents.reference_hint')}
                                    error={form.errors.reference_number}
                                    required={isMeasure}
                                >
                                    <Input
                                        {...fieldAria('reference_number', {
                                            hint: t('documents.reference_hint'),
                                            error: form.errors.reference_number,
                                        })}
                                        value={form.data.reference_number}
                                        onChange={(event) => form.setData('reference_number', event.target.value)}
                                        autoComplete="off"
                                        className="font-mono"
                                        placeholder={t('documents.reference_placeholder')}
                                    />
                                </Field>
                            </div>
                        </PanelSection>

                        {isMeasure ? (
                            <PanelSection className="space-y-4">
                                <div>
                                    <h2 className="text-sm font-semibold text-ink">{t('documents.form_measure')}</h2>
                                    <p className="mt-1 text-sm text-ink-muted">{t('documents.form_measure_hint')}</p>
                                </div>
                                <Field
                                    id="enacting_clause"
                                    label={t('documents.enacting_clause')}
                                    hint={t('documents.enacting_clause_hint')}
                                    error={form.errors.enacting_clause}
                                    required
                                >
                                    <Textarea
                                        {...fieldAria('enacting_clause', {
                                            hint: t('documents.enacting_clause_hint'),
                                            error: form.errors.enacting_clause,
                                        })}
                                        value={form.data.enacting_clause}
                                        onChange={(event) => form.setData('enacting_clause', event.target.value)}
                                        rows={3}
                                        required
                                        placeholder={t('documents.enacting_clause_placeholder')}
                                    />
                                </Field>
                                {isOrdinance ? (
                                    <Field
                                        id="explanatory_note"
                                        label={t('documents.explanatory_note')}
                                        hint={t('documents.explanatory_note_hint')}
                                        error={form.errors.explanatory_note}
                                        required
                                    >
                                        <Textarea
                                            {...fieldAria('explanatory_note', {
                                                hint: t('documents.explanatory_note_hint'),
                                                error: form.errors.explanatory_note,
                                            })}
                                            value={form.data.explanatory_note}
                                            onChange={(event) => form.setData('explanatory_note', event.target.value)}
                                            rows={4}
                                            required
                                            placeholder={t('documents.explanatory_note_placeholder')}
                                        />
                                    </Field>
                                ) : null}
                            </PanelSection>
                        ) : null}

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('documents.form_classification')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('documents.form_classification_hint')}</p>
                            </div>

                            <fieldset>
                                <legend className="text-xs font-medium text-ink">
                                    {t('documents.confidentiality')}
                                    <span aria-hidden="true" className="ml-1 text-critical">
                                        *
                                    </span>
                                </legend>
                                <div className="mt-1.5 overflow-hidden rounded-md border border-line-control">
                                    {confidentialityLevels.map((level, index) => {
                                        const selected = form.data.confidentiality === level.value;

                                        return (
                                            <label
                                                key={level.value}
                                                className={cn(
                                                    'flex cursor-pointer flex-col gap-0.5 px-3 py-2.5',
                                                    'transition-colors duration-[var(--duration-fast)]',
                                                    'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-[var(--color-focus)]',
                                                    index > 0 && 'border-t border-line',
                                                    selected && 'bg-accent-soft',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="confidentiality"
                                                    value={level.value}
                                                    checked={selected}
                                                    onChange={() => form.setData('confidentiality', level.value)}
                                                    className="sr-only"
                                                />
                                                <ConfidentialityMark
                                                    confidentiality={level.value}
                                                    label={t(`documents.confidentiality_${level.value}`)}
                                                />
                                                <span className="text-xs text-ink-muted">
                                                    {t(`documents.confidentiality_${level.value}_hint`)}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </fieldset>

                            {classificationNotice ? (
                                <Notice tone="restricted">{classificationNotice}</Notice>
                            ) : null}

                            <Field
                                id="committee_id"
                                label={t('documents.committee')}
                                hint={t('documents.committee_hint')}
                                error={form.errors.committee_id}
                            >
                                <SimpleSelect
                                    id="committee_id"
                                    value={form.data.committee_id}
                                    onValueChange={(value) => form.setData('committee_id', value)}
                                    noneLabel={t('documents.no_committee')}
                                    items={committees.map((committee) => ({
                                        value: committee.id,
                                        label: committee.name,
                                    }))}
                                />
                            </Field>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <h2 className="text-sm font-semibold text-ink">{t('documents.form_abstract')}</h2>
                            <Field
                                id="abstract"
                                label={t('documents.abstract')}
                                hint={t('documents.abstract_hint')}
                                error={form.errors.abstract}
                            >
                                <Textarea
                                    {...fieldAria('abstract', {
                                        hint: t('documents.abstract_hint'),
                                        error: form.errors.abstract,
                                    })}
                                    value={form.data.abstract}
                                    onChange={(event) => form.setData('abstract', event.target.value)}
                                    rows={4}
                                    placeholder={t('documents.abstract_placeholder')}
                                />
                            </Field>
                        </PanelSection>

                        <PanelFoot>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? t('documents.saving') : t('documents.save')}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={`/documents/${document.slug}`}>{t('documents.cancel')}</Link>
                            </Button>
                        </PanelFoot>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}

function isLegislativeMeasure(type: string): boolean {
    return ['proposed-ordinance', 'proposed-resolution', 'ordinance', 'resolution'].includes(type);
}

function isOrdinanceMeasure(type: string): boolean {
    return type === 'proposed-ordinance' || type === 'ordinance';
}
