import { Button } from '@/components/ui/button';
import { Field, fieldAria } from '@/components/ui/field';
import { Checkbox, Input, Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

/**
 * Constituting a committee names it, states its kind, and records its mandate.
 * Save is the only accent; cancel never competes with it.
 */

const TYPES = ['standing', 'special', 'ad-hoc'] as const;

export default function CommitteesCreate() {
    const { t } = useTranslations();
    const form = useForm({
        name: '',
        code: '',
        type: 'standing',
        mandate: '',
        established_on: '',
        is_active: true,
    });

    const typeNotice =
        form.data.type === 'special'
            ? t('committees.type_special_notice')
            : form.data.type === 'ad-hoc'
              ? t('committees.type_adhoc_notice')
              : null;

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            code: data.code || null,
            mandate: data.mandate || null,
            established_on: data.established_on || null,
        }));

        form.post('/committees');
    }

    return (
        <AppLayout title={t('committees.create')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader title={t('committees.create')} description={t('committees.create_subtitle')} />

                <Notice tone="info">{t('committees.create_notice')}</Notice>

                <Panel>
                    <form onSubmit={submit}>
                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('committees.form_identity')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('committees.form_identity_hint')}</p>
                            </div>

                            <Field
                                id="name"
                                label={t('committees.name')}
                                hint={t('committees.name_hint')}
                                error={form.errors.name}
                                required
                            >
                                <Input
                                    {...fieldAria('name', { hint: t('committees.name_hint'), error: form.errors.name })}
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    required
                                    autoComplete="off"
                                    placeholder={t('committees.name_placeholder')}
                                />
                            </Field>

                            <Field
                                id="code"
                                label={t('committees.code')}
                                hint={t('committees.code_hint')}
                                error={form.errors.code}
                            >
                                <Input
                                    {...fieldAria('code', { hint: t('committees.code_hint'), error: form.errors.code })}
                                    value={form.data.code}
                                    onChange={(event) => form.setData('code', event.target.value)}
                                    autoComplete="off"
                                    className="font-mono"
                                    placeholder={t('committees.code_placeholder')}
                                />
                            </Field>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('committees.form_kind')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('committees.form_kind_hint')}</p>
                            </div>

                            <fieldset>
                                <legend className="text-xs font-medium text-ink">
                                    {t('committees.type')}
                                    <span aria-hidden="true" className="ml-1 text-critical">
                                        *
                                    </span>
                                </legend>
                                <div className="mt-1.5 overflow-hidden rounded-md border border-line-control">
                                    {TYPES.map((type, index) => {
                                        const selected = form.data.type === type;
                                        const key = type === 'ad-hoc' ? 'adhoc' : type;

                                        return (
                                            <label
                                                key={type}
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
                                                    name="type"
                                                    value={type}
                                                    checked={selected}
                                                    onChange={() => form.setData('type', type)}
                                                    className="sr-only"
                                                />
                                                <span className="text-sm font-medium text-ink">
                                                    {t(`committees.type_${key}`)}
                                                </span>
                                                <span className="text-xs text-ink-muted">
                                                    {t(`committees.type_${key}_hint`)}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </fieldset>

                            {typeNotice ? <Notice tone="info">{typeNotice}</Notice> : null}
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <h2 className="text-sm font-semibold text-ink">{t('committees.form_mandate')}</h2>
                            <Field
                                id="mandate"
                                label={t('committees.mandate')}
                                hint={t('committees.mandate_hint')}
                                error={form.errors.mandate}
                            >
                                <Textarea
                                    {...fieldAria('mandate', {
                                        hint: t('committees.mandate_hint'),
                                        error: form.errors.mandate,
                                    })}
                                    value={form.data.mandate}
                                    onChange={(event) => form.setData('mandate', event.target.value)}
                                    rows={4}
                                    placeholder={t('committees.mandate_placeholder')}
                                />
                            </Field>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('committees.form_standing')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('committees.form_standing_hint')}</p>
                            </div>

                            <Field
                                id="established_on"
                                label={t('committees.established_on')}
                                hint={t('committees.established_on_hint')}
                                error={form.errors.established_on}
                            >
                                <Input
                                    {...fieldAria('established_on', {
                                        hint: t('committees.established_on_hint'),
                                        error: form.errors.established_on,
                                    })}
                                    type="date"
                                    value={form.data.established_on}
                                    onChange={(event) => form.setData('established_on', event.target.value)}
                                />
                            </Field>

                            <label htmlFor="is_active" className="flex items-start gap-2.5">
                                <Checkbox
                                    id="is_active"
                                    className="mt-0.5"
                                    checked={form.data.is_active}
                                    onChange={(event) => form.setData('is_active', event.target.checked)}
                                />
                                <span>
                                    <span className="block text-sm font-medium text-ink">{t('committees.is_active')}</span>
                                    <span className="block text-xs text-ink-muted">{t('committees.is_active_hint')}</span>
                                </span>
                            </label>

                            {!form.data.is_active ? (
                                <Notice tone="caution">{t('committees.inactive_notice')}</Notice>
                            ) : null}
                        </PanelSection>

                        <PanelFoot>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? t('committees.saving') : t('committees.save')}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href="/committees">{t('committees.cancel')}</Link>
                            </Button>
                        </PanelFoot>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}
