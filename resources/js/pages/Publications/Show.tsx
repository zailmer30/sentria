import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { StatusChip, toneForState } from '@/components/ui/status';
import { UserAvatar } from '@/components/users/UserAvatar';
import AppLayout from '@/layouts/AppLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Check,
    Circle,
    Copy,
    ExternalLink,
    FileText,
    Globe,
    Link2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';

type WorkflowStep = {
    key: string;
    label: string;
    state: 'cleared' | 'current' | 'pending';
};

type ActivityItem = {
    id: string;
    actor: string | null;
    kind: 'moved' | 'created' | 'event';
    stage: string | null;
    occurred_at: string | null;
};

type ReleaseTarget = {
    key: string;
    label: string;
    detail: string;
    href: string | null;
    available: boolean;
};

type PublicationDetail = {
    id: string;
    public_slug: string;
    title: string;
    summary: string | null;
    status: string;
    status_label: string;
    published_at: string | null;
    updated_at: string | null;
    visibility: string;
    visibility_label: string;
    categories: string[];
    redaction_applied: boolean;
    reviewed_at: string | null;
    reviewer: string | null;
    publisher: string | null;
    view_count: number;
    workflow: WorkflowStep[];
    transitions: { to: string; label: string }[];
    activity: ActivityItem[];
    release_targets: ReleaseTarget[];
    document: {
        id: string;
        slug: string;
        title: string;
        reference_number: string | null;
        document_type: string;
        document_type_label: string;
        confidentiality: string;
        confidentiality_label: string;
    } | null;
};

type Props = {
    publication: PublicationDetail;
    can: { create: boolean; transition: boolean };
};

const cardClass = 'overflow-hidden rounded-[var(--radius-md)] shadow-xs';

const TARGET_ICON = {
    portal: Globe,
    feed: Link2,
    gazette: CalendarDays,
};

export default function PublicationsShow({ publication, can }: Props) {
    const { t } = useTranslations();
    const { formatDate, formatRelative } = useFormatters();
    const published = publication.status === 'published';
    const reference = publication.document?.reference_number ?? publication.public_slug;
    const nextTransition = publication.transitions[0] ?? null;
    const hasWorkflow = can.transition && nextTransition !== null;

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(window.location.href);
            toast.success(t('publications.link_copied'));
        } catch {
            toast.error(t('publications.link_copy_failed'));
        }
    }

    function advance(to: string) {
        router.post(`/publications/${publication.public_slug}/transition`, { to });
    }

    return (
        <AppLayout title={publication.title}>
            <div className="mx-auto flex max-w-6xl flex-col gap-5">
                <header className="flex flex-col gap-4">
                    <p className="text-eyebrow text-ink-faint">
                        <Link href="/publications" className="hover:text-ink">
                            {t('nav.publications')}
                        </Link>
                        <span aria-hidden="true"> / </span>
                        <span>{t('publications.breadcrumb_record')}</span>
                    </p>

                    <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                        <div className="min-w-0 max-w-3xl">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="text-2xl font-bold tracking-tight text-floor-plate dark:text-accent">
                                    {reference}
                                </h1>
                                <span className="inline-flex items-center rounded-xs border border-line bg-canvas-sunk px-2 py-0.5 text-2xs font-semibold tracking-[0.06em] text-ink-muted uppercase">
                                    {publication.visibility === 'internal'
                                        ? t('publications.internal_document')
                                        : publication.visibility_label}
                                </span>
                                <StatusChip tone={toneForState(publication.status)}>
                                    {publication.status_label}
                                </StatusChip>
                            </div>
                            <p className="mt-2 text-lg font-medium text-ink">{publication.title}</p>
                            <p className="mt-1.5 text-sm text-ink-muted">
                                {publication.document?.document_type_label ?? t('publications.title')}
                                {publication.document?.title && publication.document.title !== publication.title
                                    ? ` · ${publication.document.title}`
                                    : null}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="secondary" asChild>
                                <Link href="/publications">{t('publications.back_to_registry')}</Link>
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                        <nav
                            aria-label={t('publications.record_tabs')}
                            className="inline-flex flex-wrap items-center gap-0.5 rounded-[var(--radius-md)] border border-line bg-surface-alt p-1"
                        >
                            {publication.document ? (
                                <Link href={`/documents/${publication.document.slug}`} className={tabClass(false)}>
                                    {t('publications.view_document')}
                                </Link>
                            ) : (
                                <span aria-disabled="true" className={cn(tabClass(false), 'cursor-not-allowed opacity-45')}>
                                    {t('publications.view_document')}
                                </span>
                            )}
                            <span className={tabClass(true)} aria-current="page">
                                {t('publications.title')}
                            </span>
                            {published ? (
                                <Link href={`/portal/documents/${publication.public_slug}`} className={tabClass(false)}>
                                    {t('publications.view_portal')}
                                </Link>
                            ) : (
                                <span aria-disabled="true" className={cn(tabClass(false), 'cursor-not-allowed opacity-45')}>
                                    {t('publications.view_portal')}
                                </span>
                            )}
                        </nav>

                        <div className="inline-flex flex-wrap items-center gap-0.5 rounded-[var(--radius-md)] border border-line bg-surface p-1 shadow-xs">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 rounded-[var(--radius-sm)] font-medium text-ink-muted hover:text-ink"
                                onClick={() => void copyLink()}
                            >
                                <Copy aria-hidden="true" strokeWidth={1.75} />
                                {t('publications.copy_link')}
                            </Button>
                            {publication.document ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 rounded-[var(--radius-sm)] font-medium text-ink-muted hover:text-ink"
                                    asChild
                                >
                                    <Link href={`/documents/${publication.document.slug}`}>
                                        <FileText aria-hidden="true" strokeWidth={1.75} />
                                        {t('publications.view_document')}
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </header>

                {published ? (
                    <Notice tone="info">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p>{t('publications.live_hint')}</p>
                            <Button variant="secondary" size="sm" asChild>
                                <Link href={`/portal/documents/${publication.public_slug}`}>
                                    {t('publications.view_portal')}
                                    <ExternalLink aria-hidden="true" strokeWidth={1.75} />
                                </Link>
                            </Button>
                        </div>
                    </Notice>
                ) : null}

                {hasWorkflow ? (
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-md)] border border-line bg-surface px-4 py-3.5 shadow-xs">
                        <div className="min-w-0 max-w-2xl">
                            <p className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span className="text-2xs font-semibold tracking-[0.14em] text-accent uppercase">
                                    {t('publications.next_step')}
                                </span>
                                <span aria-hidden="true" className="text-ink-faint">
                                    /
                                </span>
                                <span className="text-sm font-semibold text-ink">{nextTransition.label}</span>
                            </p>
                            <p className="mt-1 text-sm leading-relaxed text-ink-muted">
                                {t('publications.advance_hint', { stage: nextTransition.label })}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {publication.document ? (
                                <Button variant="ghost" size="sm" className="text-ink-muted hover:text-ink" asChild>
                                    <Link href={`/documents/${publication.document.slug}`}>
                                        {t('publications.return_to_source')}
                                    </Link>
                                </Button>
                            ) : null}
                            <Button variant="plate" size="sm" onClick={() => advance(nextTransition.to)}>
                                {t('publications.advance_to', { stage: nextTransition.label })}
                                <ArrowRight aria-hidden="true" strokeWidth={2} />
                            </Button>
                        </div>
                    </div>
                ) : null}

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20.5rem]">
                    <div className="flex min-w-0 flex-col gap-5">
                        {publication.summary ? (
                            <Panel className={cardClass}>
                                <PanelHead>
                                    <PanelTitle>{t('publications.summary')}</PanelTitle>
                                </PanelHead>
                                <PanelBody>
                                    <p className="text-sm leading-relaxed text-ink">{publication.summary}</p>
                                </PanelBody>
                            </Panel>
                        ) : null}

                        <Panel className={cardClass}>
                            <PanelHead>
                                <PanelTitle>{t('publications.workflow_heading')}</PanelTitle>
                            </PanelHead>
                            <PanelBody>
                                <ol className="grid gap-3 sm:grid-cols-2">
                                    {publication.workflow.map((step) => (
                                        <li
                                            key={step.key}
                                            className={cn(
                                                'flex items-start gap-2.5 rounded-[8px] border px-3.5 py-3',
                                                step.state === 'cleared' && 'border-success-line bg-success-soft',
                                                step.state === 'current' && 'border-accent-line bg-accent-soft',
                                                step.state === 'pending' && 'border-line bg-canvas-sunk/80',
                                            )}
                                        >
                                            <StepIcon state={step.state} />
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold text-ink">{step.label}</p>
                                                <p
                                                    className={cn(
                                                        'mt-0.5 text-xs',
                                                        step.state === 'cleared' && 'text-success',
                                                        step.state === 'current' && 'text-ink-muted',
                                                        step.state === 'pending' && 'text-ink-faint',
                                                    )}
                                                >
                                                    {t(`publications.step_${step.state}`)}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </PanelBody>
                        </Panel>

                        <Panel className={cardClass}>
                            <PanelHead>
                                <PanelTitle>{t('publications.release_targets')}</PanelTitle>
                            </PanelHead>
                            <PanelBody className="px-5 py-0">
                                <ul className="divide-y divide-line">
                                    {publication.release_targets.map((target) => {
                                        const Icon = TARGET_ICON[target.key as keyof typeof TARGET_ICON] ?? Globe;

                                        return (
                                            <li key={target.key} className="flex items-center gap-3 py-3.5">
                                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas-sunk text-ink-muted">
                                                    <Icon aria-hidden="true" className="size-4" strokeWidth={1.75} />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-medium text-ink">{target.label}</p>
                                                    <p className="truncate font-mono text-2xs text-ink-faint">{target.detail}</p>
                                                </div>
                                                {target.available && target.href ? (
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <a href={target.href} target="_blank" rel="noreferrer">
                                                            {t('publications.preview')}
                                                            <ExternalLink aria-hidden="true" strokeWidth={1.75} />
                                                        </a>
                                                    </Button>
                                                ) : (
                                                    <span className="text-2xs text-ink-faint">
                                                        {t('publications.preview_after_publish')}
                                                    </span>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </PanelBody>
                        </Panel>
                    </div>

                    <aside className="flex flex-col gap-5 lg:sticky lg:top-4">
                        <Panel className={cn(cardClass, 'pt-0')}>
                            <div className="bg-floor-plate px-5 py-3">
                                <p className="text-2xs font-semibold tracking-[0.14em] text-floor-ink uppercase">
                                    {t('documents.filing_kicker')} / {t('publications.filing_metadata')}
                                </p>
                            </div>
                            <PanelBody className="px-5 py-1">
                                <dl>
                                    <FilingRow label={t('publications.status')} accent>
                                        {publication.status_label}
                                    </FilingRow>
                                    <FilingRow label={t('publications.visibility')}>
                                        {publication.visibility_label}
                                    </FilingRow>
                                    <FilingRow label={t('publications.reference')}>{reference}</FilingRow>
                                    {publication.document ? (
                                        <FilingRow label={t('publications.source')}>
                                            {publication.document.document_type_label}
                                        </FilingRow>
                                    ) : null}
                                    <FilingRow label={t('publications.reviewer')}>
                                        {publication.reviewer ?? '—'}
                                    </FilingRow>
                                    <FilingRow label={t('publications.publisher')}>
                                        {publication.publisher ?? '—'}
                                    </FilingRow>
                                    <FilingRow label={t('publications.published_at')}>
                                        {publication.published_at
                                            ? formatDate(publication.published_at)
                                            : t('publications.not_published')}
                                    </FilingRow>
                                    <FilingRow label={t('publications.views')}>{publication.view_count}</FilingRow>
                                    {publication.categories.length > 0 ? (
                                        <FilingRow label={t('publications.categories')}>
                                            <span className="flex flex-wrap justify-end gap-1">
                                                {publication.categories.map((category) => (
                                                    <Badge key={category} variant="secondary">
                                                        {category}
                                                    </Badge>
                                                ))}
                                            </span>
                                        </FilingRow>
                                    ) : null}
                                </dl>
                            </PanelBody>
                        </Panel>

                        <Panel className={cardClass}>
                            <PanelHead>
                                <PanelTitle>{t('publications.activity')}</PanelTitle>
                            </PanelHead>
                            <PanelBody>
                                <ol className="flex flex-col gap-4">
                                    {publication.activity.map((item) => (
                                        <li key={item.id} className="flex gap-3">
                                            <UserAvatar name={item.actor} className="size-8" />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm text-ink-muted">
                                                    <span className="font-semibold text-ink">
                                                        {item.actor ?? t('publications.activity_system')}
                                                    </span>{' '}
                                                    {activityCopy(item, t)}
                                                </p>
                                                <p className="mt-0.5 text-2xs text-ink-faint">
                                                    {formatRelative(item.occurred_at)}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </PanelBody>
                        </Panel>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

function tabClass(active: boolean): string {
    return cn(
        'inline-flex h-8 items-center rounded-[var(--radius-sm)] px-3 text-sm transition-colors',
        active
            ? 'bg-surface font-medium text-ink shadow-xs ring-1 ring-line'
            : 'text-ink-muted hover:bg-surface/70 hover:text-ink',
    );
}

function FilingRow({
    label,
    children,
    accent = false,
}: {
    label: string;
    children: ReactNode;
    accent?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-line py-2.5 last:border-b-0">
            <dt className="shrink-0 text-xs text-ink-subtle">{label}</dt>
            <dd
                className={cn(
                    'min-w-0 text-right text-xs font-medium text-ink',
                    accent && 'font-semibold tracking-[0.04em] text-accent uppercase',
                )}
            >
                {children}
            </dd>
        </div>
    );
}

function activityCopy(
    item: ActivityItem,
    t: (key: string, replacements?: Record<string, string | number>) => string,
): string {
    if (item.kind === 'moved' && item.stage) {
        return t('publications.activity_moved', { stage: item.stage });
    }

    if (item.kind === 'created') {
        return t('publications.activity_created');
    }

    return t('publications.activity_updated');
}

function StepIcon({ state }: { state: WorkflowStep['state'] }) {
    if (state === 'cleared') {
        return (
            <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-success text-ink-inverse">
                <Check aria-hidden="true" className="size-3" strokeWidth={2.5} />
            </span>
        );
    }

    if (state === 'current') {
        return (
            <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-[1.5px] border-accent text-accent">
                <Check aria-hidden="true" className="size-3" strokeWidth={2.5} />
            </span>
        );
    }

    return <Circle aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-ink-faint" strokeWidth={1.5} />;
}
