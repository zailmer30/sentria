import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Field, fieldAria } from '@/components/ui/field';
import { Input, Textarea } from '@/components/ui/input';
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
    approved_on?: string | null;
    vetoed_on?: string | null;
    veto_overridden_on?: string | null;
    effectivity_date: string | null;
    publication_date?: string | null;
    publication_medium?: string | null;
    sp_submitted_on?: string | null;
    sp_reviewed_on?: string | null;
    sp_result?: string | null;
};

type Props = {
    ordinance: Ordinance | null;
    documents: { id: string; title: string; reference_number: string | null }[];
};

const STATUSES = ['draft', 'pending', 'enacted', 'vetoed', 'repealed'] as const;

export default function OrdinancesForm({ ordinance, documents }: Props) {
    const { t } = useTranslations();
    const isEdit = ordinance !== null;

    const form = useForm({
        document_id: ordinance?.document_id ?? documents[0]?.id ?? '',
        ordinance_number: ordinance?.ordinance_number ?? '',
        series_year: ordinance?.series_year ?? new Date().getFullYear(),
        title: ordinance?.title ?? '',
        purpose: ordinance?.purpose ?? '',
        status: ordinance?.status ?? 'draft',
        enacted_on: ordinance?.enacted_on ?? '',
        approved_on: ordinance?.approved_on ?? '',
        vetoed_on: ordinance?.vetoed_on ?? '',
        veto_overridden_on: ordinance?.veto_overridden_on ?? '',
        effectivity_date: ordinance?.effectivity_date ?? '',
        publication_date: ordinance?.publication_date ?? '',
        publication_medium: ordinance?.publication_medium ?? '',
        sp_submitted_on: ordinance?.sp_submitted_on ?? '',
        sp_reviewed_on: ordinance?.sp_reviewed_on ?? '',
        sp_result: ordinance?.sp_result ?? '',
    });

    const statusNotice =
        form.data.status === 'enacted'
            ? t('legislation.status_enacted_notice')
            : form.data.status === 'vetoed'
              ? t('legislation.status_vetoed_notice')
              : form.data.status === 'repealed'
                ? t('legislation.status_repealed_notice')
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
            approved_on: data.approved_on || null,
            vetoed_on: data.vetoed_on || null,
            veto_overridden_on: data.veto_overridden_on || null,
            effectivity_date: data.effectivity_date || null,
            publication_date: data.publication_date || null,
            publication_medium: data.publication_medium || null,
            sp_submitted_on: data.sp_submitted_on || null,
            sp_reviewed_on: data.sp_reviewed_on || null,
            sp_result: data.sp_result || null,
        }));

        if (isEdit && ordinance) {
            form.put(`/ordinances/${ordinance.id}`);
        } else {
            form.post('/ordinances');
        }
    }

    const cancelHref = isEdit && ordinance ? `/ordinances/${ordinance.id}` : '/ordinances';

    return (
        <AppLayout title={isEdit ? t('legislation.edit_ordinance') : t('legislation.create_ordinance')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader
                    title={isEdit ? t('legislation.edit_ordinance') : t('legislation.create_ordinance')}
                    description={isEdit ? t('legislation.edit_ordinance_subtitle') : t('legislation.create_ordinance_subtitle')}
                />

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
                    <>
                        <Notice tone="info">
                            {isEdit ? t('legislation.edit_ordinance_notice') : t('legislation.create_ordinance_notice')}
                        </Notice>

                        <Panel>
                            <form onSubmit={submit}>
                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_document')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">{t('legislation.form_document_hint')}</p>
                                    </div>

                                    <Field
                                        id="document_id"
                                        label={t('legislation.linked_document')}
                                        hint={t('legislation.linked_document_hint')}
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
                                        <p className="mt-1 text-sm text-ink-muted">{t('legislation.form_identity_hint')}</p>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="ordinance_number"
                                            label={t('legislation.number')}
                                            hint={t('legislation.number_hint')}
                                            error={form.errors.ordinance_number}
                                            required
                                        >
                                            <Input
                                                {...fieldAria('ordinance_number', {
                                                    hint: t('legislation.number_hint'),
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
                                            hint={t('legislation.year_hint')}
                                            error={form.errors.series_year}
                                            required
                                        >
                                            <Input
                                                {...fieldAria('series_year', {
                                                    hint: t('legislation.year_hint'),
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
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_status')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">{t('legislation.form_status_hint')}</p>
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
                                        <Notice
                                            tone={
                                                form.data.status === 'vetoed'
                                                    ? 'danger'
                                                    : form.data.status === 'repealed'
                                                      ? 'caution'
                                                      : 'info'
                                            }
                                        >
                                            {statusNotice}
                                        </Notice>
                                    ) : null}
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <h2 className="text-sm font-semibold text-ink">{t('legislation.form_purpose')}</h2>
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
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_dates')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">{t('legislation.form_dates_hint')}</p>
                                    </div>

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
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <div>
                                        <h2 className="text-sm font-semibold text-ink">{t('legislation.form_post_passage')}</h2>
                                        <p className="mt-1 text-sm text-ink-muted">{t('legislation.form_post_passage_hint')}</p>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="approved_on"
                                            label={t('legislation.approved_on')}
                                            error={form.errors.approved_on}
                                        >
                                            <Input
                                                id="approved_on"
                                                type="date"
                                                value={form.data.approved_on}
                                                onChange={(event) => form.setData('approved_on', event.target.value)}
                                            />
                                        </Field>
                                        <Field id="vetoed_on" label={t('legislation.vetoed_on')} error={form.errors.vetoed_on}>
                                            <Input
                                                id="vetoed_on"
                                                type="date"
                                                value={form.data.vetoed_on}
                                                onChange={(event) => form.setData('vetoed_on', event.target.value)}
                                            />
                                        </Field>
                                        <Field
                                            id="veto_overridden_on"
                                            label={t('legislation.veto_overridden_on')}
                                            error={form.errors.veto_overridden_on}
                                        >
                                            <Input
                                                id="veto_overridden_on"
                                                type="date"
                                                value={form.data.veto_overridden_on}
                                                onChange={(event) => form.setData('veto_overridden_on', event.target.value)}
                                            />
                                        </Field>
                                        <Field
                                            id="publication_date"
                                            label={t('legislation.publication_date')}
                                            error={form.errors.publication_date}
                                        >
                                            <Input
                                                id="publication_date"
                                                type="date"
                                                value={form.data.publication_date}
                                                onChange={(event) => form.setData('publication_date', event.target.value)}
                                            />
                                        </Field>
                                    </div>
                                    <Field
                                        id="publication_medium"
                                        label={t('legislation.publication_medium')}
                                        hint={t('legislation.publication_medium_hint')}
                                        error={form.errors.publication_medium}
                                    >
                                        <Input
                                            id="publication_medium"
                                            value={form.data.publication_medium}
                                            onChange={(event) => form.setData('publication_medium', event.target.value)}
                                        />
                                    </Field>
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
                                    <Field id="sp_result" label={t('legislation.sp_result')} error={form.errors.sp_result}>
                                        <SimpleSelect
                                            id="sp_result"
                                            value={form.data.sp_result}
                                            onValueChange={(value) => form.setData('sp_result', value)}
                                            noneLabel={t('legislation.sp_result_none')}
                                            items={[
                                                { value: 'consistent', label: t('legislation.sp_result_consistent') },
                                                { value: 'invalid', label: t('legislation.sp_result_invalid') },
                                                { value: 'presumed', label: t('legislation.sp_result_presumed') },
                                            ]}
                                        />
                                    </Field>
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
