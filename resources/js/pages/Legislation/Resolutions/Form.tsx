import {
    MeasureActions,
    MeasureCitation,
    MeasurePage,
    MeasureSection,
    MeasureStatusPicker,
    MeasureWorkspace,
} from '@/components/legislation/MeasureForm';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Field, fieldAria } from '@/components/ui/field';
import { Input, Textarea } from '@/components/ui/input';
import { SimpleSelect } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout';
import { EMPTY_VALUE } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { Scale } from 'lucide-react';
import { FormEvent } from 'react';

/**
 * Recording a resolution cites a source document and gives the measure its
 * number, series, and force. Save is the only accent; cancel never competes.
 */

type Resolution = {
    id: string;
    document_id: string;
    resolution_number: string;
    series_year: number;
    title: string;
    purpose: string | null;
    status: string;
    category: string | null;
    adopted_on: string | null;
    effectivity_date: string | null;
    transmitted_on?: string | null;
    transmitted_to?: string | null;
};

type DocumentOption = { id: string; title: string; reference_number: string | null };

type Props = {
    resolution: Resolution | null;
    documents: DocumentOption[];
    nextNumber?: string;
    seriesYear?: number;
};

const STATUSES = ['draft', 'pending', 'adopted', 'withdrawn'] as const;

const CATEGORIES = ['commendation', 'authorization', 'request', 'policy', 'appropriation'] as const;

const autoFieldClass = 'cursor-default bg-surface-alt text-ink-muted focus-visible:ring-0';

export default function ResolutionsForm({ resolution, documents, nextNumber, seriesYear }: Props) {
    const { t } = useTranslations();
    const isEdit = resolution !== null;
    const numberHint = isEdit ? t('legislation.number_hint_resolution') : t('legislation.number_auto_hint');
    const yearHint = isEdit ? t('legislation.year_hint_resolution') : t('legislation.year_auto_hint');
    const pageTitle = isEdit ? t('legislation.edit_resolution') : t('legislation.create_resolution');

    const form = useForm({
        document_id: resolution?.document_id ?? documents[0]?.id ?? '',
        resolution_number: resolution?.resolution_number ?? nextNumber ?? '',
        series_year: resolution?.series_year ?? seriesYear ?? new Date().getFullYear(),
        title: resolution?.title ?? '',
        purpose: resolution?.purpose ?? '',
        category: resolution?.category ?? '',
        status: resolution?.status ?? 'draft',
        adopted_on: resolution?.adopted_on ?? '',
        effectivity_date: resolution?.effectivity_date ?? '',
        transmitted_on: resolution?.transmitted_on ?? '',
        transmitted_to: resolution?.transmitted_to ?? '',
    });

    const statusNotice =
        form.data.status === 'adopted'
            ? { tone: 'info' as const, text: t('legislation.status_adopted_notice') }
            : form.data.status === 'withdrawn'
              ? { tone: 'caution' as const, text: t('legislation.status_withdrawn_notice') }
              : null;

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!form.data.document_id) {
            form.setError('document_id', t('legislation.document_required'));

            return;
        }

        form.transform((data) => ({
            ...data,
            purpose: data.purpose || null,
            category: data.category || null,
            adopted_on: data.adopted_on || null,
            effectivity_date: data.effectivity_date || null,
            transmitted_on: data.transmitted_on || null,
            transmitted_to: data.transmitted_to || null,
        }));

        if (isEdit && resolution) {
            form.put(`/resolutions/${resolution.id}`);
        } else {
            form.post('/resolutions');
        }
    }

    const cancelHref = isEdit && resolution ? `/resolutions/${resolution.id}` : '/resolutions';
    const linked = documents.find((document) => document.id === form.data.document_id);
    const linkedLabel = linked
        ? `${linked.reference_number ? `${linked.reference_number} — ` : ''}${linked.title}`
        : EMPTY_VALUE;
    const categoryLabel = form.data.category
        ? t(`legislation.category_${form.data.category}`)
        : t('legislation.category_none');

    return (
        <AppLayout title={pageTitle}>
            <MeasurePage
                title={pageTitle}
                description={isEdit ? t('legislation.edit_resolution_subtitle') : t('legislation.create_resolution_subtitle')}
            >
                {documents.length === 0 ? (
                    <EmptyState
                        icon={Scale}
                        title={t('legislation.no_documents')}
                        description={t('legislation.no_documents_hint_resolution')}
                        action={
                            <div className="flex flex-wrap items-center justify-center gap-2">
                                <Button variant="primary" asChild>
                                    <Link href="/documents?submit=1">{t('documents.create')}</Link>
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href="/resolutions">{t('legislation.cancel')}</Link>
                                </Button>
                            </div>
                        }
                    />
                ) : (
                    <MeasureWorkspace
                        form={
                            <form onSubmit={submit} className="flex min-w-0 flex-col gap-4">
                                <MeasureSection
                                    step="01"
                                    title={t('legislation.form_document')}
                                    hint={t('legislation.form_document_hint_resolution')}
                                >
                                    <Field
                                        id="document_id"
                                        label={t('legislation.linked_document')}
                                        hint={t('legislation.linked_document_hint_resolution')}
                                        error={form.errors.document_id}
                                        required
                                    >
                                        <SimpleSelect
                                            id="document_id"
                                            value={form.data.document_id}
                                            onValueChange={(value) => form.setData('document_id', value)}
                                            aria-invalid={Boolean(form.errors.document_id)}
                                            items={documents.map((document) => ({
                                                value: document.id,
                                                label: `${document.reference_number ? `${document.reference_number} — ` : ''}${document.title}`,
                                            }))}
                                        />
                                    </Field>
                                </MeasureSection>

                                <MeasureSection
                                    step="02"
                                    title={t('legislation.form_identity')}
                                    hint={t('legislation.form_identity_hint_resolution')}
                                >
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="resolution_number"
                                            label={t('legislation.number')}
                                            hint={numberHint}
                                            error={form.errors.resolution_number}
                                            required={isEdit}
                                        >
                                            <Input
                                                {...fieldAria('resolution_number', {
                                                    hint: numberHint,
                                                    error: form.errors.resolution_number,
                                                })}
                                                value={form.data.resolution_number}
                                                onChange={(event) => form.setData('resolution_number', event.target.value)}
                                                required={isEdit}
                                                readOnly={!isEdit}
                                                autoComplete="off"
                                                className={cn('font-mono', !isEdit && autoFieldClass)}
                                                placeholder={t('legislation.number_placeholder')}
                                            />
                                        </Field>
                                        <Field
                                            id="series_year"
                                            label={t('legislation.year')}
                                            hint={yearHint}
                                            error={form.errors.series_year}
                                            required={isEdit}
                                        >
                                            <Input
                                                {...fieldAria('series_year', {
                                                    hint: yearHint,
                                                    error: form.errors.series_year,
                                                })}
                                                type="number"
                                                min={1900}
                                                max={2100}
                                                value={form.data.series_year}
                                                onChange={(event) => form.setData('series_year', Number(event.target.value))}
                                                required={isEdit}
                                                readOnly={!isEdit}
                                                className={cn(!isEdit && autoFieldClass)}
                                            />
                                        </Field>
                                    </div>

                                    <Field
                                        id="title"
                                        label={t('legislation.title')}
                                        hint={t('legislation.title_hint_resolution')}
                                        error={form.errors.title}
                                        required
                                    >
                                        <Input
                                            {...fieldAria('title', {
                                                hint: t('legislation.title_hint_resolution'),
                                                error: form.errors.title,
                                            })}
                                            value={form.data.title}
                                            onChange={(event) => form.setData('title', event.target.value)}
                                            required
                                            autoComplete="off"
                                            placeholder={t('legislation.title_placeholder_resolution')}
                                        />
                                    </Field>

                                    <Field
                                        id="category"
                                        label={t('legislation.category')}
                                        hint={t('legislation.category_hint')}
                                        error={form.errors.category}
                                    >
                                        <SimpleSelect
                                            id="category"
                                            value={form.data.category}
                                            onValueChange={(value) => form.setData('category', value)}
                                            noneLabel={t('legislation.category_none')}
                                            items={CATEGORIES.map((category) => ({
                                                value: category,
                                                label: t(`legislation.category_${category}`),
                                            }))}
                                        />
                                    </Field>
                                </MeasureSection>

                                <MeasureSection
                                    step="03"
                                    title={t('legislation.form_status')}
                                    hint={t('legislation.form_status_hint_resolution')}
                                >
                                    <MeasureStatusPicker
                                        name="status"
                                        label={t('legislation.status')}
                                        value={form.data.status}
                                        statuses={STATUSES}
                                        onChange={(status) => form.setData('status', status)}
                                        labelFor={(status) => t(`legislation.status_${status}`)}
                                        hintFor={(status) => t(`legislation.status_${status}_hint`)}
                                    />
                                </MeasureSection>

                                <MeasureSection step="04" title={t('legislation.form_purpose')}>
                                    <Field
                                        id="purpose"
                                        label={t('legislation.purpose')}
                                        hint={t('legislation.purpose_hint_resolution')}
                                        error={form.errors.purpose}
                                    >
                                        <Textarea
                                            {...fieldAria('purpose', {
                                                hint: t('legislation.purpose_hint_resolution'),
                                                error: form.errors.purpose,
                                            })}
                                            value={form.data.purpose}
                                            onChange={(event) => form.setData('purpose', event.target.value)}
                                            rows={4}
                                            placeholder={t('legislation.purpose_placeholder_resolution')}
                                        />
                                    </Field>
                                </MeasureSection>

                                <MeasureSection
                                    step="05"
                                    title={t('legislation.form_dates')}
                                    hint={t('legislation.form_dates_hint_resolution')}
                                >
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="adopted_on"
                                            label={t('legislation.adopted_on')}
                                            hint={t('legislation.adopted_on_hint')}
                                            error={form.errors.adopted_on}
                                        >
                                            <Input
                                                {...fieldAria('adopted_on', {
                                                    hint: t('legislation.adopted_on_hint'),
                                                    error: form.errors.adopted_on,
                                                })}
                                                type="date"
                                                value={form.data.adopted_on}
                                                onChange={(event) => form.setData('adopted_on', event.target.value)}
                                            />
                                        </Field>
                                        <Field
                                            id="effectivity_date"
                                            label={t('legislation.effectivity_date')}
                                            hint={t('legislation.effectivity_date_hint_resolution')}
                                            error={form.errors.effectivity_date}
                                        >
                                            <Input
                                                {...fieldAria('effectivity_date', {
                                                    hint: t('legislation.effectivity_date_hint_resolution'),
                                                    error: form.errors.effectivity_date,
                                                })}
                                                type="date"
                                                value={form.data.effectivity_date}
                                                onChange={(event) => form.setData('effectivity_date', event.target.value)}
                                            />
                                        </Field>
                                    </div>
                                </MeasureSection>

                                <MeasureSection
                                    step="06"
                                    title={t('legislation.form_review')}
                                    hint={t('legislation.form_review_hint')}
                                >
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="transmitted_on"
                                            label={t('legislation.transmitted_on')}
                                            hint={t('legislation.transmitted_on_hint')}
                                            error={form.errors.transmitted_on}
                                        >
                                            <Input
                                                {...fieldAria('transmitted_on', {
                                                    hint: t('legislation.transmitted_on_hint'),
                                                    error: form.errors.transmitted_on,
                                                })}
                                                type="date"
                                                value={form.data.transmitted_on}
                                                onChange={(event) => form.setData('transmitted_on', event.target.value)}
                                            />
                                        </Field>
                                        <Field
                                            id="transmitted_to"
                                            label={t('legislation.transmitted_to')}
                                            hint={t('legislation.transmitted_to_hint')}
                                            error={form.errors.transmitted_to}
                                        >
                                            <Input
                                                {...fieldAria('transmitted_to', {
                                                    hint: t('legislation.transmitted_to_hint'),
                                                    error: form.errors.transmitted_to,
                                                })}
                                                value={form.data.transmitted_to}
                                                onChange={(event) => form.setData('transmitted_to', event.target.value)}
                                            />
                                        </Field>
                                    </div>
                                </MeasureSection>

                                <MeasureActions
                                    processing={form.processing}
                                    saveLabel={t('legislation.save')}
                                    savingLabel={t('legislation.saving')}
                                    cancelHref={cancelHref}
                                    cancelLabel={t('legislation.cancel')}
                                />
                            </form>
                        }
                        aside={
                            <MeasureCitation
                                kind={t('legislation.kind_resolution')}
                                title={form.data.title}
                                untitled={t('legislation.untitled_resolution')}
                                numberLine={t('legislation.resolution_no', {
                                    number: form.data.resolution_number || EMPTY_VALUE,
                                })}
                                seriesLine={t('legislation.series_of', { year: form.data.series_year })}
                                statusLabel={t(`legislation.status_${form.data.status}`)}
                                notice={
                                    isEdit
                                        ? t('legislation.edit_resolution_notice')
                                        : t('legislation.create_resolution_notice')
                                }
                                statusNotice={statusNotice}
                                facts={[
                                    {
                                        label: t('legislation.number'),
                                        value: form.data.resolution_number || EMPTY_VALUE,
                                        mono: true,
                                    },
                                    { label: t('legislation.year'), value: String(form.data.series_year), mono: true },
                                    { label: t('legislation.status'), value: t(`legislation.status_${form.data.status}`) },
                                    { label: t('legislation.category'), value: categoryLabel },
                                    { label: t('legislation.linked_document'), value: linkedLabel },
                                    { label: t('legislation.adopted_on'), value: form.data.adopted_on || EMPTY_VALUE, mono: true },
                                    {
                                        label: t('legislation.effectivity_date'),
                                        value: form.data.effectivity_date || EMPTY_VALUE,
                                        mono: true,
                                    },
                                    {
                                        label: t('legislation.transmitted_to'),
                                        value: form.data.transmitted_to || EMPTY_VALUE,
                                    },
                                ]}
                            />
                        }
                    />
                )}
            </MeasurePage>
        </AppLayout>
    );
}
