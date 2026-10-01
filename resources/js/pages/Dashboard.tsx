import { ActionTile, ActionTileRow } from '@/components/ui/action-tile';
import { Card } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { EmptyState } from '@/components/ui/empty-state';
import { Progress } from '@/components/ui/progress';
import { RangeToggle, type RangeOption } from '@/components/ui/range-toggle';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Bot,
    Building2,
    CalendarDays,
    Check,
    FilePlus,
    FileText,
    Gavel,
    LayoutPanelTop,
    Package,
    Scale,
    ShieldCheck,
    Sparkles,
    type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Area, AreaChart, CartesianGrid, Cell, Pie, PieChart, XAxis } from 'recharts';

/**
 * The dashboard is a set of answers to "what needs me now", and every widget on
 * it is optional: the server sends a payload only for the questions this user
 * is permitted to ask. So there is no per-role page and no client-side hiding —
 * eight roles read the same component and see eight different pages, because
 * absent data means an absent widget.
 */

type PipelineStage = { key: string; count: number };

type RecentFiling = {
    id: string;
    slug: string | null;
    reference_number: string | null;
    title: string;
    status: string;
    status_label: string;
    submitted_at: string | null;
};

type Sitting = {
    id: string;
    title: string;
    type: string;
    type_label: string;
    starts_at: string | null;
};

type QueueItem = { key: string; done: number; total: number; percent: number };

type DashboardMetrics = {
    documents?: {
        total: number;
        in_range: number;
        delta: number | null;
        month_added: number;
        awaiting_action: number;
        due_this_week: number;
        enacted_ytd: number;
        enacted_prior_year: number;
        restricted: number;
        in_review: number;
        pipeline: PipelineStage[];
        standings: PipelineStage[];
        composition: PipelineStage[];
        recent: RecentFiling[];
        intake: { bucket: string; count: number; filed: number; published: number }[];
    };
    session?: {
        live: boolean;
        id: string;
        title: string;
        session_number: string | null;
        status: string;
        starts_at: string | null;
        venue: string | null;
        quorum: { seated_count: number; present_count: number; required: number; met: boolean } | null;
        agenda_count: number;
    };
    sittings?: Sitting[];
    minutes?: { awaiting: number; finalized: number; total: number };
    publications?: { live: number; in_review: number };
    referrals?: {
        open: number;
        overdue: number;
        items: {
            id: string;
            document_title: string;
            document_slug: string | null;
            committee: string | null;
            due_at: string | null;
            overdue: boolean;
        }[];
    };
    processing?: QueueItem[];
    activity?: {
        id: string;
        event: string | null;
        category: string | null;
        message: string | null;
        actor: string | null;
        is_ai_actor: boolean;
        occurred_at: string | null;
    }[];
};

type QuickAction = { key: string; href: string; label: string; description: string };

type DashboardProps = {
    role: string | null;
    headline: string;
    givenName: string;
    range: string;
    rangeOptions: RangeOption[];
    metrics: DashboardMetrics;
    actions: QuickAction[];
};

const ACTION_ICONS: Record<string, LucideIcon> = {
    'file-document': FilePlus,
    'session-floor': Gavel,
    'review-legislation': Scale,
    publish: Building2,
    'ask-ai': Bot,
    audit: ShieldCheck,
};

const COMPOSITION_COLORS: Record<string, string> = {
    ordinances: 'var(--color-chart-1)',
    resolutions: 'var(--color-chart-2)',
    minutes: 'var(--color-chart-3)',
    committee_reports: 'var(--color-chart-4)',
};

const STANDING_TONES: Record<string, string> = {
    intake: 'text-accent',
    committee: 'text-[var(--color-chart-2)]',
    floor: 'text-[var(--color-chart-3)]',
    publication: 'text-[var(--color-chart-4)]',
};

const QUEUE_TONES: Record<string, string> = {
    ocr: 'text-accent',
    committee: 'text-[var(--color-chart-2)]',
    publication: 'text-[var(--color-chart-4)]',
};

const SITTING_EDGES: Record<string, string> = {
    regular: 'border-l-accent',
    special: 'border-l-[var(--color-chart-4)]',
    'committee-hearing': 'border-l-[var(--color-chart-2)]',
    'public-hearing': 'border-l-[var(--color-chart-3)]',
};

export default function Dashboard({ role, headline, givenName, range, rangeOptions, metrics, actions }: DashboardProps) {
    const { ai } = usePage<PageProps>().props;
    const { t, locale } = useTranslations();
    const [pending, setPending] = useState(range);

    const options = rangeOptions.map((option) => ({
        ...option,
        description: t(option.description),
    }));

    function changeRange(next: string) {
        setPending(next);
        router.get('/dashboard', { range: next }, { only: ['metrics', 'range'], preserveScroll: true, preserveState: true });
    }

    const { documents, session, sittings, publications, referrals, processing, activity } = metrics;
    const nothingToShow = !documents && !session && !sittings && !publications && !referrals && !activity && !processing;

    const officeKey = role ? `dashboard.office.${role}` : 'dashboard.office.default';
    const office = t(officeKey);
    const officeLabel = office === officeKey ? t('dashboard.office.default') : office;

    return (
        <AppLayout title={t(headline)}>
            <div className="flex flex-col gap-4">
                <Hero
                    office={officeLabel}
                    name={givenName}
                    copy={heroCopy(t, locale, documents?.awaiting_action ?? 0, session)}
                    sessionHref={session ? `/sessions/${session.id}` : '/sessions'}
                    rings={[
                        referrals
                            ? {
                                  key: 'overdue',
                                  label: t('dashboard.hero.overdue'),
                                  value: referrals.overdue,
                                  max: Math.max(referrals.open, referrals.overdue),
                                  caption: t('dashboard.hero.pressed'),
                              }
                            : null,
                        documents
                            ? {
                                  key: 'review',
                                  label: t('dashboard.hero.in_review'),
                                  value: documents.in_review,
                                  caption: t('dashboard.hero.documents'),
                              }
                            : null,
                        publications
                            ? {
                                  key: 'published',
                                  label: t('dashboard.hero.published'),
                                  value: publications.live,
                                  caption: t('dashboard.hero.public_records'),
                              }
                            : null,
                    ].filter((ring): ring is HeroRingData => ring !== null)}
                />

                {nothingToShow ? (
                    <EmptyState icon={LayoutPanelTop} title={t('dashboard.empty_title')} description={t('dashboard.empty')} />
                ) : null}

                {documents ? (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <KpiCard
                            label={t('dashboard.documents_total')}
                            value={documents.total}
                            detail={
                                <p className={cn('text-xs', documents.month_added > 0 ? 'text-success' : 'text-ink-subtle')}>
                                    {documents.month_added > 0
                                        ? t('dashboard.this_month', { count: documents.month_added })
                                        : t('dashboard.this_month_none')}
                                </p>
                            }
                        />
                        <KpiCard
                            label={t('dashboard.awaiting_action')}
                            value={documents.awaiting_action}
                            tone="text-accent"
                            detail={
                                <p className="text-xs text-ink-subtle">
                                    {t('dashboard.due_this_week', { count: documents.due_this_week })}
                                </p>
                            }
                        />
                        <KpiCard
                            label={t('dashboard.enacted_ytd')}
                            value={documents.enacted_ytd}
                            detail={
                                <p
                                    className={cn(
                                        'text-xs',
                                        documents.enacted_ytd - documents.enacted_prior_year > 0
                                            ? 'text-success'
                                            : 'text-ink-subtle',
                                    )}
                                >
                                    {t('dashboard.vs_year', {
                                        count: signedDelta(documents.enacted_ytd - documents.enacted_prior_year),
                                        year: new Date().getFullYear() - 1,
                                    })}
                                </p>
                            }
                        />
                        <KpiCard
                            label={t('dashboard.restricted_holdings')}
                            value={documents.restricted}
                            tone="text-live"
                            detail={<p className="text-xs text-ink-subtle">{t('dashboard.restricted_hint')}</p>}
                        />
                    </div>
                ) : null}

                {documents ? (
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                        <FilingChart
                            documents={documents}
                            range={pending}
                            options={options}
                            onRangeChange={changeRange}
                        />
                        <CompositionChart composition={documents.composition} />
                    </div>
                ) : null}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    {documents ? (
                        <Card className="gap-0 overflow-hidden rounded-[var(--radius-xl)] p-0 lg:col-span-8">
                            <div className="flex items-center justify-between gap-3 px-5 pt-5 pb-3">
                                <h2 className="text-sm font-semibold text-ink">{t('dashboard.recent_filings')}</h2>
                                <Link
                                    href="/documents"
                                    className="text-xs font-medium text-accent underline-offset-2 hover:underline"
                                >
                                    {t('dashboard.view_all')}
                                </Link>
                            </div>

                            {documents.recent.length === 0 ? (
                                <EmptyState bare icon={FileText} title={t('dashboard.recent_empty')} className="py-10" />
                            ) : (
                                <ul className="flex flex-col">
                                    {documents.recent.map((filing) => (
                                        <li key={filing.id} className="border-t border-line">
                                            <Link
                                                href={filing.slug ? `/documents/${filing.slug}` : '/documents'}
                                                className="flex items-start justify-between gap-4 px-5 py-3.5 hover:bg-canvas-sunk"
                                            >
                                                <span className="flex min-w-0 items-start gap-2.5">
                                                    <span
                                                        aria-hidden="true"
                                                        className={cn(
                                                            'mt-1.5 size-2 shrink-0 rounded-full',
                                                            filingDot(filing.status),
                                                        )}
                                                    />
                                                    <span className="min-w-0">
                                                        {filing.reference_number ? (
                                                            <span className="block font-mono text-2xs tracking-[0.04em] text-ink-faint">
                                                                {filing.reference_number}
                                                            </span>
                                                        ) : null}
                                                        <span className="block truncate text-sm font-medium text-ink">
                                                            {filing.title}
                                                        </span>
                                                    </span>
                                                </span>
                                                <span className="flex shrink-0 flex-col items-end gap-1">
                                                    <span className="rounded-[var(--radius-sm)] border border-line bg-canvas-sunk px-2 py-0.5 text-2xs text-ink-muted">
                                                        {filing.status_label}
                                                    </span>
                                                    {filing.submitted_at ? (
                                                        <span className="font-mono text-2xs text-ink-faint">
                                                            {new Date(filing.submitted_at).toLocaleDateString(locale, {
                                                                month: 'short',
                                                                day: 'numeric',
                                                                year: 'numeric',
                                                            })}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div className="border-t border-line px-5 py-5">
                                <p className="label-eyebrow">{t('dashboard.standings')}</p>
                                {documents.standings.every((stage) => stage.count === 0) ? (
                                    <p className="mt-3 text-sm text-ink-subtle">{t('dashboard.pipeline_empty')}</p>
                                ) : (
                                    <ul className="mt-4 flex flex-col gap-3.5">
                                        {documents.standings.map((stage) => (
                                            <li key={stage.key} className="flex flex-col gap-1.5">
                                                <div className="flex items-baseline justify-between gap-3">
                                                    <span className="text-sm text-ink-muted">
                                                        {t(`dashboard.stage.${stage.key}`)}
                                                    </span>
                                                    <span className="font-mono text-xs font-medium text-ink">
                                                        {stage.count}
                                                    </span>
                                                </div>
                                                <Progress
                                                    className={cn(
                                                        'h-1',
                                                        STANDING_TONES[stage.key] ?? 'text-accent',
                                                    )}
                                                    value={
                                                        documents.total > 0
                                                            ? (stage.count / documents.total) * 100
                                                            : 0
                                                    }
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </Card>
                    ) : null}

                    <div className={cn('flex flex-col gap-4', documents ? 'lg:col-span-4' : 'lg:col-span-12')}>
                        {sittings ? (
                            <Card className="rounded-[var(--radius-xl)] p-5">
                                <h2 className="text-sm font-semibold text-ink">{t('dashboard.next_sittings')}</h2>
                                {sittings.length === 0 ? (
                                    <p className="mt-3 text-sm text-ink-subtle">{t('dashboard.sittings_empty')}</p>
                                ) : (
                                    <ul className="mt-4 flex flex-col gap-3">
                                        {sittings.map((sitting) => (
                                            <li key={sitting.id}>
                                                <Link
                                                    href={`/sessions/${sitting.id}`}
                                                    className={cn(
                                                        'block rounded-r-[var(--radius-md)] border-l-[3px] py-1 pl-3',
                                                        SITTING_EDGES[sitting.type] ?? 'border-l-accent',
                                                    )}
                                                >
                                                    <span className="block text-sm font-medium text-ink">{sitting.title}</span>
                                                    {sitting.starts_at ? (
                                                        <span className="mt-0.5 block text-xs text-ink-subtle">
                                                            {formatSittingWhen(sitting.starts_at, locale)}
                                                        </span>
                                                    ) : null}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <Link
                                    href="/sessions"
                                    className="mt-4 inline-block text-xs font-medium text-accent underline-offset-2 hover:underline"
                                >
                                    {t('dashboard.open_calendar')}
                                </Link>
                            </Card>
                        ) : null}

                        {processing ? (
                            <Card className="rounded-[var(--radius-xl)] p-5">
                                <h2 className="text-sm font-semibold text-ink">{t('dashboard.processing_queue')}</h2>
                                <ul className="mt-4 flex flex-col gap-3.5">
                                    {processing.map((item) => (
                                        <li key={item.key} className="flex flex-col gap-1.5">
                                            <div className="flex items-baseline justify-between gap-3">
                                                <span className="text-sm text-ink-muted">
                                                    {t(`dashboard.processing.${item.key}`)}
                                                </span>
                                                <span className="font-mono text-xs font-medium text-ink-muted">
                                                    {item.percent}%
                                                </span>
                                            </div>
                                            <Progress
                                                className={cn('h-1', QUEUE_TONES[item.key] ?? 'text-accent')}
                                                value={item.percent}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </Card>
                        ) : null}

                        {activity ? (
                            <Card className="rounded-[var(--radius-xl)] p-5">
                                <h2 className="text-sm font-semibold text-ink">{t('dashboard.activity')}</h2>
                                {activity.length === 0 ? (
                                    <p className="mt-3 text-sm text-ink-subtle">{t('dashboard.activity_empty')}</p>
                                ) : (
                                    <ul className="mt-4 flex flex-col gap-3.5">
                                        {activity.map((entry) => {
                                            const Icon = activityIcon(entry);

                                            return (
                                                <li key={entry.id} className="flex items-start gap-3">
                                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-canvas-sunk text-ink-muted">
                                                        <Icon aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="block text-sm font-medium text-ink">
                                                            {entry.message ?? entry.event ?? ''}
                                                        </span>
                                                        <span className="mt-0.5 block truncate text-xs text-ink-subtle">
                                                            {[
                                                                entry.actor,
                                                                entry.occurred_at
                                                                    ? new Date(entry.occurred_at).toLocaleTimeString(locale, {
                                                                          hour: '2-digit',
                                                                          minute: '2-digit',
                                                                      })
                                                                    : null,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </span>
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </Card>
                        ) : null}
                    </div>
                </div>

                {actions.length > 0 ? (
                    <section aria-labelledby="quick-actions" className="flex flex-col gap-3">
                        <h2 id="quick-actions" className="label-eyebrow">
                            {t('dashboard.quick_actions')}
                        </h2>
                        <ActionTileRow>
                            {actions.map((action) => (
                                <ActionTile
                                    key={action.key}
                                    href={
                                        action.key === 'session-floor' && session
                                            ? `/sessions/${session.id}`
                                            : action.href
                                    }
                                    icon={ACTION_ICONS[action.key] ?? FileText}
                                    label={t(action.label)}
                                    description={t(action.description)}
                                />
                            ))}
                        </ActionTileRow>
                    </section>
                ) : null}

                {ai.enabled ? null : <p className="text-xs text-ink-faint">{t('ai.preview_disabled')}</p>}
            </div>
        </AppLayout>
    );
}

type HeroRingData = {
    key: string;
    label: string;
    value: number;
    max?: number;
    caption: string;
};

function Hero({
    office,
    name,
    copy,
    sessionHref,
    rings,
}: {
    office: string;
    name: string;
    copy: string;
    sessionHref: string;
    rings: HeroRingData[];
}) {
    const { t } = useTranslations();

    return (
        <section className="bg-dashboard-hero overflow-hidden rounded-[var(--radius-2xl)] px-6 py-6 text-floor-ink sm:px-8 sm:py-7">
            <div className="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0 max-w-xl">
                    <p className="text-2xs font-semibold tracking-[0.16em] text-floor-ink-faint uppercase">{office}</p>
                    <h1 className="mt-2 text-2xl font-semibold tracking-[-0.03em] text-floor-ink">
                        {t('dashboard.welcome_back', { name })}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-floor-ink-muted">{copy}</p>
                    <div className="mt-5 flex flex-wrap items-center gap-2.5">
                        <Link
                            href="/documents"
                            className="inline-flex h-9 items-center rounded-full bg-floor-ink px-4 text-sm font-medium text-desk-plate shadow-[var(--shadow-xs)] hover:bg-floor-ink/90"
                        >
                            {t('dashboard.open_documents')}
                        </Link>
                        <Link
                            href={sessionHref}
                            className="inline-flex h-9 items-center gap-1.5 rounded-full border border-floor-line px-4 text-sm font-medium text-floor-ink hover:bg-floor-sunk"
                        >
                            <CalendarDays aria-hidden="true" strokeWidth={1.75} className="size-4" />
                            {t('dashboard.session_floor')}
                        </Link>
                    </div>
                </div>

                {rings.length > 0 ? (
                    <div className="flex flex-wrap items-start gap-6 sm:gap-8">
                        {rings.map((ring) => (
                            <HeroRing key={ring.key} {...ring} />
                        ))}
                    </div>
                ) : null}
            </div>
        </section>
    );
}

function HeroRing({ label, value, max, caption }: HeroRingData) {
    const safeMax = max && max > 0 ? max : Math.max(value, 1);
    const ratio = Math.min(Math.max(value / safeMax, 0), 1);
    const radius = 26;
    const circumference = 2 * Math.PI * radius;
    const display = max !== undefined && max > 0 ? `${value}/${max}` : String(value);

    return (
        <div className="flex w-[5.5rem] flex-col items-center text-center">
            <p className="text-2xs font-semibold tracking-[0.14em] text-floor-ink-faint uppercase">{label}</p>
            <div
                className="relative mt-2 size-[4.75rem]"
                role="img"
                aria-label={`${label}: ${display} ${caption}`}
            >
                <svg viewBox="0 0 64 64" className="size-full -rotate-90" aria-hidden="true">
                    <circle
                        cx="32"
                        cy="32"
                        r={radius}
                        fill="none"
                        strokeWidth="5"
                        className="stroke-floor-line"
                    />
                    <circle
                        cx="32"
                        cy="32"
                        r={radius}
                        fill="none"
                        strokeWidth="5"
                        strokeLinecap="round"
                        className="stroke-desk-ring"
                        strokeDasharray={`${circumference * ratio} ${circumference}`}
                    />
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center">
                    <span className="font-mono text-lg font-semibold leading-none tracking-[-0.03em] text-floor-ink">
                        {display}
                    </span>
                    <span className="mt-0.5 text-2xs text-floor-ink-faint">{caption}</span>
                </div>
            </div>
        </div>
    );
}

function KpiCard({
    label,
    value,
    tone = 'text-ink',
    detail,
}: {
    label: string;
    value: number;
    tone?: string;
    detail: ReactNode;
}) {
    return (
        <Card className="rounded-[var(--radius-xl)] px-5 py-6">
            <p className="label-eyebrow">{label}</p>
            <p className={cn('mt-2 font-mono text-figure font-medium tracking-[-0.03em]', tone)}>{value}</p>
            <div className="mt-3">{detail}</div>
        </Card>
    );
}

function FilingChart({
    documents,
    range,
    options,
    onRangeChange,
}: {
    documents: NonNullable<DashboardMetrics['documents']>;
    range: string;
    options: RangeOption[];
    onRangeChange: (value: string) => void;
}) {
    const { t, locale } = useTranslations();

    const config = {
        filed: { label: t('dashboard.intake_series'), color: 'var(--color-chart-2)' },
        published: { label: t('dashboard.intake_published'), color: 'var(--color-chart-3)' },
    } satisfies ChartConfig;

    const data = documents.intake.map((point, index) => ({
        ...point,
        label: intakeLabel(point.bucket, range, index, locale),
    }));

    return (
        <Card className="rounded-[var(--radius-xl)] p-5 lg:col-span-7">
            <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold text-ink">{t('dashboard.intake')}</h2>
                    <p className="mt-0.5 text-xs text-ink-subtle">{t('dashboard.intake_hint')}</p>
                </div>
                <RangeToggle options={options} value={range} onChange={onRangeChange} label={t('dashboard.range_label')} />
            </div>

            <ChartContainer config={config} className="aspect-auto mt-4 h-[11.5rem] w-full">
                <AreaChart data={data} margin={{ left: 8, right: 8, top: 8, bottom: 0 }}>
                    <defs>
                        <linearGradient id="dashboard-filed" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="var(--color-chart-2)" stopOpacity={0.35} />
                            <stop offset="100%" stopColor="var(--color-chart-2)" stopOpacity={0.04} />
                        </linearGradient>
                        <linearGradient id="dashboard-published" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="var(--color-chart-3)" stopOpacity={0.28} />
                            <stop offset="100%" stopColor="var(--color-chart-3)" stopOpacity={0.03} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid vertical={false} strokeDasharray="3 6" />
                    <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} minTickGap={20} fontSize={11} />
                    <ChartTooltip content={<ChartTooltipContent />} />
                    <Area
                        type="monotone"
                        dataKey="published"
                        stroke="var(--color-published)"
                        fill="url(#dashboard-published)"
                        strokeWidth={1.5}
                    />
                    <Area
                        type="monotone"
                        dataKey="filed"
                        stroke="var(--color-filed)"
                        fill="url(#dashboard-filed)"
                        strokeWidth={2}
                    />
                </AreaChart>
            </ChartContainer>
        </Card>
    );
}

function CompositionChart({ composition }: { composition: PipelineStage[] }) {
    const { t } = useTranslations();
    const total = composition.reduce((sum, item) => sum + item.count, 0);

    const config = Object.fromEntries(
        composition.map((item) => [
            item.key,
            { label: t(`dashboard.composition.${item.key}`), color: COMPOSITION_COLORS[item.key] },
        ]),
    ) satisfies ChartConfig;

    return (
        <Card className="rounded-[var(--radius-xl)] p-5 lg:col-span-5">
            <h2 className="text-sm font-semibold text-ink">{t('dashboard.composition')}</h2>
            <p className="mt-0.5 text-xs text-ink-subtle">{t('dashboard.composition_hint')}</p>

            {total === 0 ? (
                <p className="mt-8 text-sm text-ink-subtle">{t('dashboard.pipeline_empty')}</p>
            ) : (
                <div className="mt-4 flex items-center gap-5">
                    <ChartContainer config={config} className="aspect-auto h-[9.5rem] w-[9.5rem] shrink-0">
                        <PieChart>
                            <Pie
                                data={composition}
                                dataKey="count"
                                nameKey="key"
                                innerRadius={38}
                                outerRadius={58}
                                paddingAngle={2}
                                strokeWidth={0}
                            >
                                {composition.map((item) => (
                                    <Cell key={item.key} fill={COMPOSITION_COLORS[item.key] ?? 'var(--color-chart-5)'} />
                                ))}
                            </Pie>
                            <ChartTooltip content={<ChartTooltipContent hideLabel />} />
                        </PieChart>
                    </ChartContainer>

                    <ul className="flex min-w-0 flex-1 flex-col gap-2.5">
                        {composition.map((item) => (
                            <li key={item.key} className="flex items-center gap-2 text-sm text-ink-muted">
                                <span
                                    aria-hidden="true"
                                    className="size-2 shrink-0 rounded-full"
                                    style={{ backgroundColor: COMPOSITION_COLORS[item.key] }}
                                />
                                <span>{t(`dashboard.composition.${item.key}`)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Card>
    );
}

function heroCopy(
    t: (key: string, replacements?: Record<string, string | number>) => string,
    locale: string,
    awaiting: number,
    session?: DashboardMetrics['session'],
): string {
    const date = session?.starts_at
        ? new Date(session.starts_at).toLocaleDateString(locale, { dateStyle: 'long' })
        : '';

    if (session?.title && date) {
        if (awaiting === 0) {
            return t('dashboard.hero.action_none_with_session', { session: session.title, date });
        }

        if (awaiting === 1) {
            return t('dashboard.hero.action_one_with_session', { session: session.title, date });
        }

        return t('dashboard.hero.action_with_session', { count: awaiting, session: session.title, date });
    }

    if (awaiting === 0) {
        return t('dashboard.hero.action_none');
    }

    if (awaiting === 1) {
        return t('dashboard.hero.action_one');
    }

    return t('dashboard.hero.action', { count: awaiting });
}

function intakeLabel(bucket: string, range: string, index: number, locale: string): string {
    if (range === '30d') {
        return `Week ${index + 1}`;
    }

    const date = new Date(bucket);

    if (range === 'ytd') {
        return date.toLocaleDateString(locale, { month: 'short' });
    }

    return date.toLocaleDateString(locale, { month: 'short', day: 'numeric' });
}

function formatSittingWhen(iso: string, locale: string): string {
    const date = new Date(iso);
    const day = date.toLocaleDateString(locale, { dateStyle: 'long' });
    const time = date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });

    return `${day} · ${time}`;
}

function signedDelta(value: number): string {
    if (value > 0) {
        return `+${value}`;
    }

    return String(value);
}

function filingDot(status: string): string {
    const key = status.toLowerCase();

    if (['approved', 'final-document', 'enacted'].includes(key)) {
        return 'bg-accent';
    }

    if (['transmittal', 'amendments', 'voting'].includes(key)) {
        return 'bg-[var(--color-chart-4)]';
    }

    if (['committee-referral', 'committee-review', 'committee-report'].includes(key)) {
        return 'bg-warning';
    }

    return 'bg-success';
}

function activityIcon(entry: NonNullable<DashboardMetrics['activity']>[number]): LucideIcon {
    if (entry.is_ai_actor) {
        return Sparkles;
    }

    const haystack = `${entry.category ?? ''} ${entry.event ?? ''} ${entry.message ?? ''}`.toLowerCase();

    if (haystack.includes('publish')) {
        return Package;
    }

    if (haystack.includes('workflow') || haystack.includes('transition')) {
        return Check;
    }

    return FileText;
}
