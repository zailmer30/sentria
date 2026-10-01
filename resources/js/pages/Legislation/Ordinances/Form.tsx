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
 * Recording an ordinance cites a source document and gives the measure its
 * number, series, and force. Save is the only accent; cancel never competes.
 */

type Ordinance = {
    id: string;
    document_id: string;
    ordinance_number: string;
    series_year: number;
    title: string;
    purpose: string | null;
    status: string;
    enacted_on: string | null;
    effectivity_date: string | null;
};

type DocumentOption = { id: string; title: string; reference_number: string | null };

type Props = {
    ordinance: Ordinance | null;
    documents: DocumentOption[];
    nextNumber?: string;
    seriesYear?: number;
};

const STATUSES = ['draft', 'pending', 'enacted', 'vetoed', 'repealed'] as const;

const autoFieldClass = 'cursor-default bg-surface-alt text-ink-muted focus-visible:ring-0';

export default function OrdinancesForm({ ordinance, documents, nextNumber, seriesYear }: Props) {
    const { t } = useTranslations();
    const isEdit = ordinance !== null;
    const numberHint = isEdit ? t('legislation.number_hint') : t('legislation.ordinance_number_auto_hint');
    const yearHint = isEdit ? t('legislation.year_hint') : t('legislation.year_auto_hint');
    const pageTitle = isEdit ? t('legislation.edit_ordinance') : t('legislation.create_ordinance');

    const form = useForm({
        document_id: ordinance?.document_id ?? documents[0]?.id ?? '',
        ordinance_number: ordinance?.ordinance_number ?? nextNumber ?? '',
        series_year: ordinance?.series_year ?? seriesYear ?? new Date().getFullYear(),
        title: ordinance?.title ?? '',
        purpose: ordinance?.purpose ?? '',
        status: ordinance?.status ?? 'draft',
        enacted_on: ordinance?.enacted_on ?? '',
        effectivity_date: ordinance?.effectivity_date ?? '',
    });

    const statusNotice =
        form.data.status === 'enacted'
            ? { tone: 'info' as const, text: t('legislation.status_enacted_notice') }
            : form.data.status === 'vetoed'
              ? { tone: 'danger' as const, text: t('legislation.status_vetoed_notice') }
              : form.data.status === 'repealed'
                ? { tone: 'caution' as const, text: t('legislation.status_repealed_notice') }
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
            enacted_on: data.enacted_on || null,
            effectivity_date: data.effectivity_date || null,
        }));

        if (isEdit && ordinance) {
            form.put(`/ordinances/${ordinance.id}`);
        } else {
            form.post('/ordinances');
        }
    }

    const cancelHref = isEdit && ordinance ? `/ordinances/${ordinance.id}` : '/ordinances';
    const linked = documents.find((document) => document.id === form.data.document_id);
    const linkedLabel = linked
        ? `${linked.reference_number ? `${linked.reference_number} — ` : ''}${linked.title}`
        : EMPTY_VALUE;

    return (
        <AppLayout title={pageTitle}>
            <MeasurePage
                title={pageTitle}
                description={isEdit ? t('legislation.edit_ordinance_subtitle') : t('legislation.create_ordinance_subtitle')}
            >
                {documents.length === 0 ? (
                    <EmptyState
                        icon={Scale}
                        title={t('legislation.no_documents')}
                        description={t('legislation.no_documents_hint')}
                        action={
                            <div className="flex flex-wrap items-center justify-center gap-2">
                                <Button variant="primary" asChild>
                                    <Link href="/documents?submit=1">{t('documents.create')}</Link>
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href="/ordinances">{t('legislation.cancel')}</Link>
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
                                    hint={t('legislation.form_document_hint')}
                                >
                                    <Field
                                        id="document_id"
                                        label={t('legislation.linked_document')}
                                        hint={t('legislation.linked_document_hint')}
                                        error={form.errors.document_id}
                                        required
                                    >
                                        <SimpleDocumentSelect
                                            id="document_id"
                                            value={form.data.document_id}
                                            error={form.errors.document_id}
                                            documents={documents}
                                            onChange={(value) => form.setData('document_id', value)}
                                        />
                                    </Field>
                                </MeasureSection>

                                <MeasureSection
                                    step="02"
                                    title={t('legislation.form_identity')}
                                    hint={t('legislation.form_identity_hint')}
                                >
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="ordinance_number"
                                            label={t('legislation.number')}
                                            hint={numberHint}
                                            error={form.errors.ordinance_number}
                                            required
                                        >
                                            <Input
                                                {...fieldAria('ordinance_number', {
                                                    hint: numberHint,
                                                    error: form.errors.ordinance_number,
                                                })}
                                                value={form.data.ordinance_number}
                                                onChange={(event) => form.setData('ordinance_number', event.target.value)}
                                                required
                                                autoComplete="off"
                                                className="font-mono"
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
                                        hint={t('legislation.title_hint')}
                                        error={form.errors.title}
                                        required
                                    >
                                        <Input
                                            {...fieldAria('title', {
                                                hint: t('legislation.title_hint'),
                                                error: form.errors.title,
                                            })}
                                            value={form.data.title}
                                            onChange={(event) => form.setData('title', event.target.value)}
                                            required
                                            autoComplete="off"
                                            placeholder={t('legislation.title_placeholder')}
                                        />
                                    </Field>
                                </MeasureSection>

                                <MeasureSection
                                    step="03"
                                    title={t('legislation.form_status')}
                                    hint={t('legislation.form_status_hint')}
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
                                        hint={t('legislation.purpose_hint')}
                                        error={form.errors.purpose}
                                    >
                                        <Textarea
                                            {...fieldAria('purpose', {
                                                hint: t('legislation.purpose_hint'),
                                                error: form.errors.purpose,
                                            })}
                                            value={form.data.purpose}
                                            onChange={(event) => form.setData('purpose', event.target.value)}
                                            rows={4}
                                            placeholder={t('legislation.purpose_placeholder')}
                                        />
                                    </Field>
                                </MeasureSection>

                                <MeasureSection
                                    step="05"
                                    title={t('legislation.form_dates')}
                                    hint={t('legislation.form_dates_hint')}
                                >
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="enacted_on"
                                            label={t('legislation.enacted_on')}
                                            hint={t('legislation.enacted_on_hint')}
                                            error={form.errors.enacted_on}
                                        >
                                            <Input
                                                {...fieldAria('enacted_on', {
                                                    hint: t('legislation.enacted_on_hint'),
                                                    error: form.errors.enacted_on,
                                                })}
                                                type="date"
                                                value={form.data.enacted_on}
                                                onChange={(event) => form.setData('enacted_on', event.target.value)}
                                            />
                                        </Field>
                                        <Field
                                            id="effectivity_date"
                                            label={t('legislation.effectivity_date')}
                                            hint={t('legislation.effectivity_date_hint')}
                                            error={form.errors.effectivity_date}
                                        >
                                            <Input
                                                {...fieldAria('effectivity_date', {
                                                    hint: t('legislation.effectivity_date_hint'),
                                                    error: form.errors.effectivity_date,
                                                })}
                                                type="date"
                                                value={form.data.effectivity_date}
                                                onChange={(event) => form.setData('effectivity_date', event.target.value)}
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
                                kind={t('legislation.kind_ordinance')}
                                title={form.data.title}
                                untitled={t('legislation.untitled_ordinance')}
                                numberLine={t('legislation.ordinance_no', {
                                    number: form.data.ordinance_number || EMPTY_VALUE,
                                })}
                                seriesLine={t('legislation.series_of', { year: form.data.series_year })}
                                statusLabel={t(`legislation.status_${form.data.status}`)}
                                notice={
                                    isEdit
                                        ? t('legislation.edit_ordinance_notice')
                                        : t('legislation.create_ordinance_notice')
                                }
                                statusNotice={statusNotice}
                                facts={[
                                    { label: t('legislation.number'), value: form.data.ordinance_number || EMPTY_VALUE, mono: true },
                                    { label: t('legislation.year'), value: String(form.data.series_year), mono: true },
                                    { label: t('legislation.status'), value: t(`legislation.status_${form.data.status}`) },
                                    { label: t('legislation.linked_document'), value: linkedLabel },
                                    { label: t('legislation.enacted_on'), value: form.data.enacted_on || EMPTY_VALUE, mono: true },
                                    {
                                        label: t('legislation.effectivity_date'),
                                        value: form.data.effectivity_date || EMPTY_VALUE,
                                        mono: true,
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

function SimpleDocumentSelect({
    id,
    value,
    error,
    documents,
    onChange,
}: {
    id: string;
    value: string;
    error?: string;
    documents: DocumentOption[];
    onChange: (value: string) => void;
}) {
    return (
        <SimpleSelect
            id={id}
            value={value}
            onValueChange={onChange}
            aria-invalid={Boolean(error)}
            items={documents.map((document) => ({
                value: document.id,
                label: `${document.reference_number ? `${document.reference_number} — ` : ''}${document.title}`,
            }))}
        />
    );
}
