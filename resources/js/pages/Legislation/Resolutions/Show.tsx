import { LegislativeHistoryPreview, type HistoryEvent } from '@/components/legislation/LegislativeHistoryPreview';
import { SignedCopyPanel, type SignedCopy } from '@/components/legislation/SignedCopyPanel';
import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody } from '@/components/ui/panel';
import { UserAvatar } from '@/components/users/UserAvatar';
import AppLayout from '@/layouts/AppLayout';
import { EMPTY_VALUE, useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    ExternalLink,
    FileText,
    Pencil,
    Send,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type LinkedDocument = {
    slug: string;
    title: string;
    reference_number: string | null;
    status_label?: string;
    author?: string | null;
    committee?: string | null;
};

type Resolution = {
    id: string;
    resolution_number: string;
    series_year: number;
    title: string;
    purpose: string | null;
    category: string | null;
    status: string;
    status_label: string | null;
    adopted_on: string | null;
    effectivity_date: string | null;
    transmitted_on?: string | null;
    transmitted_to?: string | null;
    updated_at?: string | null;
    document: LinkedDocument | null;
    signed_copy: SignedCopy | null;
    imported_at?: string | null;
};

type PublicationSummary = {
    public_slug: string;
    status: string;
    status_label: string;
};

type Props = {
    resolution: Resolution;
    publication: PublicationSummary | null;
    history: HistoryEvent[];
    can: { update: boolean; createPublication: boolean };
    signedCopyRequiresConfirmation?: boolean;
};

const cardClass = 'overflow-hidden rounded-md border border-line bg-surface shadow-sm';

const heroButtonClass =
    'border-transparent bg-surface text-ink shadow-none hover:bg-canvas-sunk hover:text-ink';

export default function ResolutionsShow({
    resolution,
    publication,
    history = [],
    can,
    signedCopyRequiresConfirmation = false,
}: Props) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();
    const statusLabel = t(`legislation.status_${resolution.status}`);
    const statusText =
        statusLabel === `legislation.status_${resolution.status}`
            ? (resolution.status_label ?? resolution.status)
            : statusLabel;
    const citation = citationCode(resolution);
    const nextMilestone = nextResolutionMilestone(resolution, publication, t, formatDate);
    const publicationValue = publication
        ? publication.status_label
        : t('legislation.publication_not_started');
    const publicationDetail = publication
        ? publication.status_label
        : t('legislation.publication_none');
    const categoryLabel = resolution.category
        ? translatedOrFallback(t, `legislation.category_${resolution.category}`, resolution.category)
        : null;

    const notice =
        resolution.status === 'adopted'
            ? t('legislation.status_adopted_notice')
            : resolution.status === 'withdrawn'
              ? t('legislation.status_withdrawn_notice')
              : null;

    function startPublication() {
        if (!resolution.document) {
            return;
        }

        router.post(`/documents/${resolution.document.slug}/publication`);
    }

    return (
        <AppLayout title={resolution.title}>
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
                    <span className="font-medium text-ink">{citation}</span>
                </nav>

                <section className={cn(cardClass, 'border-0 bg-record-hero px-6 py-6 text-floor-ink sm:px-7 sm:py-7')}>
                    <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
                        <div className="min-w-0 max-w-3xl">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="inline-flex items-center rounded-xs border border-floor-line bg-floor-sunk px-2 py-0.5 text-2xs font-medium text-floor-ink">
                                    {t('legislation.kind_resolution')}
                                </span>
                                <span className="inline-flex items-center rounded-xs border border-floor-line bg-floor-sunk px-2 py-0.5 text-2xs font-medium text-floor-ink-muted">
                                    {statusText}
                                </span>
                            </div>
                            <h1 className="mt-3 text-2xl font-semibold tracking-tight text-floor-ink sm:text-3xl">
                                {resolution.title}
                            </h1>
                            <p className="mt-3 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs tracking-[0.08em] text-floor-ink-muted uppercase">
                                <span>{t('legislation.resolution_no', { number: resolution.resolution_number })}</span>
                                <span aria-hidden="true" className="text-floor-ink-faint">
                                    ·
                                </span>
                                <span>{t('legislation.series_of', { year: resolution.series_year })}</span>
                                {resolution.updated_at ? (
                                    <>
                                        <span aria-hidden="true" className="text-floor-ink-faint">
                                            ·
                                        </span>
                                        <span className="inline-flex items-center gap-1.5 normal-case tracking-normal">
                                            <Clock aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                            {t('legislation.updated_on', { date: formatDate(resolution.updated_at) })}
                                        </span>
                                    </>
                                ) : null}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {can.update ? (
                                <Button variant="secondary" className={heroButtonClass} asChild>
                                    <Link href={`/resolutions/${resolution.id}/edit`}>
                                        <Pencil aria-hidden="true" strokeWidth={1.75} />
                                        {t('legislation.edit_record')}
                                    </Link>
                                </Button>
                            ) : null}
                            {resolution.document && !publication && can.createPublication ? (
                                <Button variant="secondary" className={heroButtonClass} onClick={startPublication}>
                                    <Send aria-hidden="true" strokeWidth={1.75} />
                                    {t('publications.start')}
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </section>

                <section className={cn(cardClass, 'grid grid-cols-1 divide-y divide-line sm:grid-cols-3 sm:divide-x sm:divide-y-0')}>
                    <StatusFact icon={FileText} label={t('legislation.record_status')} value={statusText} />
                    <StatusFact icon={Send} label={t('legislation.publication')} value={publicationValue} />
                    <StatusFact icon={CalendarDays} label={t('legislation.next_milestone')} value={nextMilestone} />
                </section>

                {resolution.imported_at ? <Notice tone="info">{t('legislation.import_record_notice')}</Notice> : null}

                {notice ? (
                    <Notice tone={resolution.status === 'adopted' ? 'info' : 'caution'}>{notice}</Notice>
                ) : null}

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_21.5rem]">
                    <div className="flex min-w-0 flex-col gap-4">
                        <Panel className={cardClass}>
                            <PanelBody className="px-6 py-6">
                                <div className="mb-4">
                                    <h2 className="text-base font-semibold tracking-tight text-ink">
                                        {t('legislation.purpose')}
                                    </h2>
                                    <p className="mt-1 text-sm text-ink-muted">
                                        {t('legislation.purpose_caption_resolution')}
                                    </p>
                                </div>
                                {resolution.purpose ? (
                                    <p className="text-sm leading-relaxed text-ink">{resolution.purpose}</p>
                                ) : (
                                    <div className="flex flex-col items-center gap-2 rounded-md border border-dashed border-line-strong bg-canvas-sunk/40 px-6 py-10 text-center">
                                        <span className="flex size-10 items-center justify-center rounded-full bg-surface text-ink-faint shadow-xs">
                                            <Pencil aria-hidden="true" strokeWidth={1.75} className="size-4" />
                                        </span>
                                        <p className="text-sm font-semibold text-ink">{t('legislation.no_purpose_title')}</p>
                                        <p className="max-w-sm text-sm text-ink-subtle">
                                            {t('legislation.no_purpose_hint_resolution')}
                                        </p>
                                        {can.update ? (
                                            <Button variant="secondary" size="sm" className="mt-1.5 rounded-full" asChild>
                                                <Link href={`/resolutions/${resolution.id}/edit`}>
                                                    {t('legislation.add_purpose')}
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                )}
                            </PanelBody>
                        </Panel>

                        <SignedCopyPanel
                            kind="resolution"
                            recordId={resolution.id}
                            signedCopy={resolution.signed_copy}
                            canManage={can.update}
                            requiresUnpublishConfirmation={signedCopyRequiresConfirmation}
                            className={cardClass}
                        />

                        <LegislativeHistoryPreview events={history} className={cardClass} />
                    </div>

                    <aside className="flex flex-col gap-4 lg:sticky lg:top-4">
                        <Panel className={cardClass}>
                            <PanelBody className="px-6 pt-5 pb-0">
                                <h2 className="text-base font-semibold tracking-tight text-ink">
                                    {t('legislation.record')}
                                </h2>
                            </PanelBody>
                            <PanelBody className="px-6 py-1">
                                <dl>
                                    <CitationRow label={t('legislation.number')} numeric>
                                        {resolution.resolution_number}
                                    </CitationRow>
                                    <CitationRow label={t('legislation.year')} numeric>
                                        {resolution.series_year}
                                    </CitationRow>
                                    <CitationRow label={t('legislation.status')}>{statusText}</CitationRow>
                                    {categoryLabel ? (
                                        <CitationRow label={t('legislation.category')}>{categoryLabel}</CitationRow>
                                    ) : null}
                                    <CitationRow label={t('legislation.adopted_on')} numeric>
                                        {formatDate(resolution.adopted_on)}
                                    </CitationRow>
                                    <CitationRow label={t('legislation.effectivity_date')} numeric>
                                        {formatDate(resolution.effectivity_date)}
                                    </CitationRow>
                                    {resolution.transmitted_on ? (
                                        <CitationRow label={t('legislation.transmitted_on')} numeric>
                                            {formatDate(resolution.transmitted_on)}
                                        </CitationRow>
                                    ) : null}
                                    {resolution.transmitted_to ? (
                                        <CitationRow label={t('legislation.transmitted_to')}>
                                            {resolution.transmitted_to}
                                        </CitationRow>
                                    ) : null}
                                </dl>
                            </PanelBody>
                            <div className="border-t border-line px-6 py-3.5">
                                <p className="text-2xs font-medium tracking-[0.04em] text-ink-subtle uppercase">
                                    {t('legislation.publication_status')}
                                </p>
                                <p className="mt-1.5 flex items-center gap-2 text-sm text-ink">
                                    <span
                                        aria-hidden="true"
                                        className="size-1.5 shrink-0 rounded-full bg-ink"
                                    />
                                    {publicationDetail}
                                </p>
                            </div>
                        </Panel>

                        <Panel className={cardClass}>
                            <PanelBody className="px-6 py-5">
                                <div className="flex items-start justify-between gap-3">
                                    <p className="label-eyebrow">{t('legislation.source_document')}</p>
                                    <span className="flex size-8 items-center justify-center rounded-sm bg-canvas-sunk text-ink-faint">
                                        <FileText aria-hidden="true" strokeWidth={1.75} className="size-4" />
                                    </span>
                                </div>
                                {resolution.document ? (
                                    <div className="mt-3 space-y-3">
                                        <div>
                                            <p className="text-sm font-semibold text-ink">{resolution.document.title}</p>
                                            <p className="mt-1 font-mono text-2xs text-ink-faint">
                                                {resolution.document.reference_number ?? t('documents.no_reference')}
                                                {resolution.document.status_label
                                                    ? ` · ${resolution.document.status_label}`
                                                    : ''}
                                            </p>
                                        </div>
                                        {resolution.document.author ? (
                                            <div className="flex items-center gap-2">
                                                <UserAvatar
                                                    name={resolution.document.author}
                                                    className="size-7"
                                                    fallbackClassName="text-2xs"
                                                />
                                                <p className="text-sm text-ink">{resolution.document.author}</p>
                                            </div>
                                        ) : null}
                                        {resolution.document.committee ? (
                                            <p className="text-sm text-ink-muted">
                                                {t('documents.committee')}: {resolution.document.committee}
                                            </p>
                                        ) : null}
                                        <Button variant="secondary" className="w-full" asChild>
                                            <Link href={`/documents/${resolution.document.slug}`}>
                                                {t('legislation.view_source_document')}
                                                <ExternalLink aria-hidden="true" strokeWidth={1.75} />
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    <p className="mt-3 text-sm text-ink-subtle">{t('legislation.no_source')}</p>
                                )}
                            </PanelBody>
                        </Panel>

                        <Link
                            href="/resolutions"
                            className="inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-ink"
                        >
                            <ArrowLeft aria-hidden="true" strokeWidth={1.75} className="size-4" />
                            {t('legislation.back_to_resolutions')}
                        </Link>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

/**
 * Next work on the resolution record itself — adoption, publication, or
 * effectivity. Legislative history is a log of what already happened on the
 * linked document and is not a source for this slot.
 */
function nextResolutionMilestone(
    resolution: Resolution,
    publication: PublicationSummary | null,
    t: (key: string, replacements?: Record<string, string | number>) => string,
    formatDate: (value: string | null | undefined) => string,
): string {
    if (resolution.status === 'withdrawn') {
        return EMPTY_VALUE;
    }

    if (resolution.status === 'draft' || resolution.status === 'pending') {
        return t('legislation.milestone_adoption');
    }

    const published = publication?.status === 'published' || publication?.status === 'mark-public';

    if (!published) {
        return t('legislation.publication');
    }

    if (resolution.effectivity_date) {
        const when = new Date(resolution.effectivity_date);

        if (!Number.isNaN(when.getTime()) && when.getTime() > Date.now()) {
            return t('legislation.milestone_effectivity', { date: formatDate(resolution.effectivity_date) });
        }
    }

    return EMPTY_VALUE;
}

function citationCode(resolution: Resolution): string {
    const number = resolution.resolution_number.trim();

    if (/^res\b/i.test(number)) {
        return number;
    }

    return `RES-${resolution.series_year}-${number}`;
}

function translatedOrFallback(
    t: (key: string) => string,
    key: string,
    fallback: string,
): string {
    const label = t(key);

    return label === key ? fallback : label;
}

function StatusFact({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-3 px-6 py-5">
            <div className="min-w-0">
                <p className="text-xs text-ink-muted">{label}</p>
                <p className="mt-1 truncate text-sm font-semibold text-ink">{value}</p>
            </div>
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent">
                <Icon aria-hidden="true" strokeWidth={1.75} className="size-4" />
            </span>
        </div>
    );
}

function CitationRow({
    label,
    children,
    numeric = false,
}: {
    label: string;
    children: ReactNode;
    numeric?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-line py-2.5 last:border-b-0">
            <dt className="shrink-0 text-xs text-ink-subtle">{label}</dt>
            <dd className={cn('min-w-0 text-right text-sm text-ink', numeric && 'font-mono text-xs')}>{children}</dd>
        </div>
    );
}
