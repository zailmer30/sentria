import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Field, fieldAria } from '@/components/ui/field';
import { Checkbox, Input, Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import { StatusChip, toneForState } from '@/components/ui/status';
import AppLayout from '@/layouts/AppLayout';
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
    lce_sp_required?: boolean;
    sp_submitted_on?: string | null;
    sp_reviewed_on?: string | null;
    sp_result?: string | null;
};

type Props = {
    resolution: Resolution | null;
    documents: { id: string; title: string; reference_number: string | null }[];
};

const STATUSES = ['draft', 'pending', 'adopted', 'withdrawn'] as const;

const CATEGORIES = ['commendation', 'authorization', 'request', 'policy', 'appropriation'] as const;

export default function ResolutionsForm({ resolution, documents }: Props) {
    const { t } = useTranslations();
    const isEdit = resolution !== null;

    const form = useForm({
        document_id: resolution?.document_id ?? documents[0]?.id ?? '',
        resolution_number: resolution?.resolution_number ?? '',
        series_year: resolution?.series_year ?? new Date().getFullYear(),
        title: resolution?.title ?? '',
        purpose: resolution?.purpose ?? '',
        category: resolution?.category ?? '',
        status: resolution?.status ?? 'draft',
        adopted_on: resolution?.adopted_on ?? '',
        effectivity_date: resolution?.effectivity_date ?? '',
        transmitted_on: resolution?.transmitted_on ?? '',
        transmitted_to: resolution?.transmitted_to ?? '',
        lce_sp_required: resolution?.lce_sp_required ?? false,
        sp_submitted_on: resolution?.sp_submitted_on ?? '',
        sp_reviewed_on: resolution?.sp_reviewed_on ?? '',
        sp_result: resolution?.sp_result ?? '',
    });

    const statusNotice =
        form.data.status === 'adopted'
            ? t('legislation.status_adopted_notice')
            : form.data.status === 'withdrawn'
              ? t('legislation.status_withdrawn_notice')
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
            sp_submitted_on: data.sp_submitted_on || null,
            sp_reviewed_on: data.sp_reviewed_on || null,
            sp_result: data.sp_result || null,
        }));

        if (isEdit && resolution) {
            form.put(`/resolutions/${resolution.id}`);
        } else {
            form.post('/resolutions');
        }
    }

    const cancelHref = isEdit && resolution ? `/resolutions/${resolution.id}` : '/resolutions';

    return (
        <AppLayout title={isEdit ? t('legislation.edit_resolution') : t('legislation.create_resolution')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader
                    title={isEdit ? t('legislation.edit_resolution') : t('legislation.create_resolution')}
                    description={isEdit ? t('legislation.edit_resolution_subtitle') : t('legislation.create_resolution_subtitle')}
                />

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
                    <>
                        <Notice tone="info">
                            {isEdit ? t('legislation.edit_resolution_notice') : t('legislation.create_resolution_notice')}
                        </Notice>

                        <Panel>
                            <form onSubmit={submit}>
                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_document')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">
                                            {t('legislation.form_document_hint_resolution')}
                                        </p>
                                    </div>

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
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_identity')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">
                                            {t('legislation.form_identity_hint_resolution')}
                                        </p>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="resolution_number"
                                            label={t('legislation.number')}
                                            hint={t('legislation.number_hint_resolution')}
                                            error={form.errors.resolution_number}
                                            required
                                        >
                                            <Input
                                                {...fieldAria('resolution_number', {
                                                    hint: t('legislation.number_hint_resolution'),
                                                    error: form.errors.resolution_number,
                                                })}
                                                value={form.data.resolution_number}
                                                onChange={(event) => form.setData('resolution_number', event.target.value)}
                                                required
                                                autoComplete="off"
                                                className="font-mono"
                                                placeholder={t('legislation.number_placeholder')}
                                            />
                                        </Field>
                                        <Field
                                            id="series_year"
                                            label={t('legislation.year')}
                                            hint={t('legislation.year_hint_resolution')}
                                            error={form.errors.series_year}
                                            required
                                        >
                                            <Input
                                                {...fieldAria('series_year', {
                                                    hint: t('legislation.year_hint_resolution'),
                                                    error: form.errors.series_year,
                                                })}
                                                type="number"
                                                min={1900}
                                                max={2100}
                                                value={form.data.series_year}
                                                onChange={(event) => form.setData('series_year', Number(event.target.value))}
                                                required
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
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_status')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">
                                            {t('legislation.form_status_hint_resolution')}
                                        </p>
                                    </div>

                                    <fieldset>
                                        <legend className="text-xs font-medium text-ink">
                                            {t('legislation.status')}
                                            <span aria-hidden="true" className="ml-1 text-critical">
                                                *
                                            </span>
                                        </legend>
                                        <div className="mt-1.5 overflow-hidden rounded-md border border-line-control">
                                            {STATUSES.map((status, index) => {
                                                const selected = form.data.status === status;

                                                return (
                                                    <label
                                                        key={status}
                                                        className={cn(
                                                            'flex cursor-pointer flex-col gap-1 px-3 py-2.5',
                                                            'transition-colors duration-[var(--duration-fast)]',
                                                            'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-[var(--color-focus)]',
                                                            index > 0 && 'border-t border-line',
                                                            selected && 'bg-accent-soft',
                                                        )}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="status"
                                                            value={status}
                                                            checked={selected}
                                                            onChange={() => form.setData('status', status)}
                                                            className="sr-only"
                                                        />
                                                        <StatusChip tone={toneForState(status)} size="sm">
                                                            {t(`legislation.status_${status}`)}
                                                        </StatusChip>
                                                        <span className="text-xs text-ink-muted">
                                                            {t(`legislation.status_${status}_hint`)}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </fieldset>

                                    {statusNotice ? (
                                        <Notice tone={form.data.status === 'withdrawn' ? 'caution' : 'info'}>
                                            {statusNotice}
                                        </Notice>
                                    ) : null}
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <h2 className="text-sm font-semibold text-ink">{t('legislation.form_purpose')}</h2>
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
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_dates')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">
                                            {t('legislation.form_dates_hint_resolution')}
                                        </p>
                                    </div>

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
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_review')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">{t('legislation.form_review_hint')}</p>
                                    </div>

                                    <label htmlFor="lce_sp_required" className="flex items-start gap-2.5">
                                        <Checkbox
                                            id="lce_sp_required"
                                            className="mt-0.5"
                                            checked={form.data.lce_sp_required}
                                            onChange={(event) => form.setData('lce_sp_required', event.target.checked)}
                                        />
                                        <span>
                                            <span className="block text-sm font-medium text-ink">
                                                {t('legislation.lce_sp_required')}
                                            </span>
                                            <span className="block text-xs text-ink-muted">
                                                {t('legislation.lce_sp_required_hint')}
                                            </span>
                                        </span>
                                    </label>

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

                                    {form.data.lce_sp_required ? (
                                        <>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Field
                                                    id="sp_submitted_on"
                                                    label={t('legislation.sp_submitted_on')}
                                                    error={form.errors.sp_submitted_on}
                                                >
                                                    <Input
                                                        id="sp_submitted_on"
                                                        type="date"
                                                        value={form.data.sp_submitted_on}
                                                        onChange={(event) => form.setData('sp_submitted_on', event.target.value)}
                                                    />
                                                </Field>
                                                <Field
                                                    id="sp_reviewed_on"
                                                    label={t('legislation.sp_reviewed_on')}
                                                    error={form.errors.sp_reviewed_on}
                                                >
                                                    <Input
                                                        id="sp_reviewed_on"
                                                        type="date"
                                                        value={form.data.sp_reviewed_on}
                                                        onChange={(event) => form.setData('sp_reviewed_on', event.target.value)}
                                                    />
                                                </Field>
                                            </div>
                                            <Field
                                                id="sp_result"
                                                label={t('legislation.sp_result')}
                                                error={form.errors.sp_result}
                                            >
                                                <SimpleSelect
                                                    id="sp_result"
                                                    value={form.data.sp_result}
                                                    onValueChange={(value) => form.setData('sp_result', value)}
                                                    noneLabel={t('legislation.sp_result_none')}
                                                    items={[
                                                        {
                                                            value: 'consistent',
                                                            label: t('legislation.sp_result_consistent'),
                                                        },
                                                        {
                                                            value: 'invalid',
                                                            label: t('legislation.sp_result_invalid'),
                                                        },
                                                        {
                                                            value: 'presumed',
                                                            label: t('legislation.sp_result_presumed'),
                                                        },
                                                    ]}
                                                />
                                            </Field>
                                        </>
                                    ) : null}
                                </PanelSection>

                                <PanelFoot>
                                    <Button type="submit" variant="primary" disabled={form.processing}>
                                        {form.processing ? t('legislation.saving') : t('legislation.save')}
                                    </Button>
                                    <Button variant="ghost" asChild>
                                        <Link href={cancelHref}>{t('legislation.cancel')}</Link>
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
