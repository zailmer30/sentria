import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelFoot } from '@/components/ui/panel';
import { StatusChip, toneForState } from '@/components/ui/status';
import { EMPTY_VALUE } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Shared chrome for recording an ordinance or a resolution: a page heading,
 * numbered sections, a live citation slip, and one accent on save.
 */

const cardClass = 'overflow-hidden rounded-md border border-line bg-surface shadow-sm';

const floorChip =
    'inline-flex items-center rounded-xs border border-floor-line bg-floor-sunk px-2 py-0.5 text-2xs font-medium';

type MeasurePageProps = {
    title: string;
    description: string;
    children: ReactNode;
};

export function MeasurePage({ title, description, children }: MeasurePageProps) {
    const { t } = useTranslations();

    return (
        <div className="mx-auto flex max-w-6xl flex-col gap-4">
            <nav aria-label={t('nav.group_record')} className="text-xs text-ink-muted">
                <span>{t('nav.group_record')}</span>
                <span aria-hidden="true" className="px-1.5 text-ink-faint">
                    ›
                </span>
                <Link href="/legislation" className="hover:text-ink">
                    {t('nav.legislation')}
                </Link>
                <span aria-hidden="true" className="px-1.5 text-ink-faint">
                    ›
                </span>
                <span className="font-medium text-ink">{title}</span>
            </nav>

            <PageHeader title={title} description={description} />
            {children}
        </div>
    );
}

export function MeasureWorkspace({ form, aside }: { form: ReactNode; aside: ReactNode }) {
    return (
        <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            {form}
            <aside className="flex flex-col gap-4 lg:sticky lg:top-4">{aside}</aside>
        </div>
    );
}

type MeasureSectionProps = {
    step: string;
    title: string;
    hint?: string;
    children: ReactNode;
};

export function MeasureSection({ step, title, hint, children }: MeasureSectionProps) {
    return (
        <Panel className={cardClass}>
            <PanelBody className="space-y-5 px-5 py-5 sm:px-6">
                <div className="flex items-start gap-3">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent tabular-nums">
                        {step}
                    </span>
                    <div className="min-w-0 pt-0.5">
                        <h2 className="text-sm font-semibold tracking-tight text-ink">{title}</h2>
                        {hint ? <p className="mt-1 text-sm leading-relaxed text-ink-muted">{hint}</p> : null}
                    </div>
                </div>
                {children}
            </PanelBody>
        </Panel>
    );
}

type MeasureStatusPickerProps = {
    name: string;
    label: string;
    value: string;
    statuses: readonly string[];
    onChange: (status: string) => void;
    labelFor: (status: string) => string;
    hintFor: (status: string) => string;
};

export function MeasureStatusPicker({
    name,
    label,
    value,
    statuses,
    onChange,
    labelFor,
    hintFor,
}: MeasureStatusPickerProps) {
    return (
        <fieldset>
            <legend className="sr-only">
                {label}
                <span aria-hidden="true"> *</span>
            </legend>
            <div className="grid gap-2 sm:grid-cols-2">
                {statuses.map((status) => {
                    const selected = value === status;

                    return (
                        <label
                            key={status}
                            className={cn(
                                'flex cursor-pointer flex-col gap-2 rounded-md border px-3.5 py-3',
                                'transition-[background-color,border-color,box-shadow] duration-[var(--duration-fast)]',
                                'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-[var(--color-focus)]',
                                selected
                                    ? 'border-accent bg-accent-soft shadow-[var(--shadow-xs)]'
                                    : 'border-line bg-surface hover:border-line-strong hover:bg-surface-alt',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={status}
                                checked={selected}
                                onChange={() => onChange(status)}
                                className="sr-only"
                            />
                            <span className="flex items-center justify-between gap-2">
                                <StatusChip tone={toneForState(status)} size="sm">
                                    {labelFor(status)}
                                </StatusChip>
                                <Check
                                    aria-hidden="true"
                                    strokeWidth={2}
                                    className={cn('size-3.5 text-accent', selected ? 'opacity-100' : 'opacity-0')}
                                />
                            </span>
                            <span className="text-xs leading-relaxed text-ink-muted">{hintFor(status)}</span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}

type CitationFact = {
    label: string;
    value: string;
    mono?: boolean;
};

type MeasureCitationProps = {
    kind: string;
    title: string;
    untitled: string;
    numberLine: string;
    seriesLine: string;
    statusLabel: string;
    facts: CitationFact[];
    notice: string;
    statusNotice?: { tone: 'info' | 'caution' | 'danger'; text: string } | null;
};

export function MeasureCitation({
    kind,
    title,
    untitled,
    numberLine,
    seriesLine,
    statusLabel,
    facts,
    notice,
    statusNotice,
}: MeasureCitationProps) {
    const { t } = useTranslations();
    const hasTitle = title.trim().length > 0;

    return (
        <>
            <section className="overflow-hidden rounded-md bg-record-hero px-5 py-5 text-floor-ink">
                <p className="text-2xs font-medium tracking-[0.14em] text-floor-ink-muted uppercase">
                    {t('legislation.form_preview')}
                </p>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <span className={cn(floorChip, 'text-floor-ink')}>{kind}</span>
                    <span className={cn(floorChip, 'text-floor-ink-muted')}>{statusLabel}</span>
                </div>
                <p
                    className={cn(
                        'mt-3 text-lg leading-snug font-semibold tracking-tight',
                        hasTitle ? 'text-floor-ink' : 'text-floor-ink-muted',
                    )}
                >
                    {hasTitle ? title : untitled}
                </p>
                <p className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs tracking-[0.08em] text-floor-ink-muted uppercase">
                    <span>{numberLine}</span>
                    <span aria-hidden="true" className="text-floor-ink-faint">
                        ·
                    </span>
                    <span>{seriesLine}</span>
                </p>
                <p className="mt-4 text-xs leading-relaxed text-floor-ink-muted normal-case tracking-normal">
                    {t('legislation.form_preview_hint')}
                </p>
            </section>

            <Panel className={cardClass}>
                <PanelBody className="px-5 py-4">
                    <p className="label-eyebrow">{t('legislation.record')}</p>
                    <dl className="mt-1">
                        {facts.map((fact) => (
                            <div
                                key={fact.label}
                                className="flex items-start justify-between gap-4 border-b border-line py-2.5 last:border-b-0"
                            >
                                <dt className="shrink-0 text-xs text-ink-subtle">{fact.label}</dt>
                                <dd
                                    className={cn(
                                        'min-w-0 text-right text-sm text-ink',
                                        fact.mono && 'font-mono text-xs',
                                        fact.value === EMPTY_VALUE && 'text-ink-faint',
                                    )}
                                >
                                    {fact.value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </PanelBody>
            </Panel>

            <Notice tone="info">{notice}</Notice>
            {statusNotice ? <Notice tone={statusNotice.tone}>{statusNotice.text}</Notice> : null}
        </>
    );
}

type MeasureActionsProps = {
    processing: boolean;
    saveLabel: string;
    savingLabel: string;
    cancelHref: string;
    cancelLabel: string;
};

export function MeasureActions({ processing, saveLabel, savingLabel, cancelHref, cancelLabel }: MeasureActionsProps) {
    return (
        <Panel className={cardClass}>
            <PanelFoot className="justify-end bg-surface px-5 py-3.5 sm:px-6">
                <Button variant="ghost" asChild>
                    <Link href={cancelHref}>{cancelLabel}</Link>
                </Button>
                <Button type="submit" variant="primary" disabled={processing}>
                    {processing ? savingLabel : saveLabel}
                </Button>
            </PanelFoot>
        </Panel>
    );
}
