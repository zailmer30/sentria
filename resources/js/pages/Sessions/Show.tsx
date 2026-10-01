import { AgendaBuilderToolbar, AgendaList, type MinutesConsideration } from '@/components/session/AgendaBuilder';
import { CalendarDocket } from '@/components/session/CalendarDocket';
import { QuorumCard, type QuorumSummary } from '@/components/session/QuorumCard';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Panel, PanelHead, PanelTitle } from '@/components/ui/panel';
import { LiveDot, toneForState } from '@/components/ui/status';
import AppLayout from '@/layouts/AppLayout';
import { EMPTY_VALUE, useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { floorPath, preferredFloorPath, sessionDisplayTitle } from '@/lib/sessionFloor';
import { cn } from '@/lib/utils';
import type { CalendarDocketItem } from '@/pages/Sessions/Floor/shared';
import type { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    CalendarCheck,
    CalendarClock,
    Check,
    ClipboardCheck,
    ClipboardList,
    Gavel,
    Hash,
    LayoutDashboard,
    ListOrdered,
    Loader2,
    Monitor,
    Pause,
    Pencil,
    Play,
    ScrollText,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';

/**
 * One sitting, stated once: where it is in the machine, what the next lawful
 * move is, whether the room has a quorum, and the order of business.
 *
 * The sitting's identity sits on the chamber plate — the same deep navy the
 * floor surfaces use — because this page is where staff stand up a sitting and
 * then walk into the room with it. Everything below the plate is a desk
 * surface: the destinations it leads to, the transitions the current state can
 * actually take, the counted facts, and the order of business.
 */

type AgendaItem = {
    id: string;
    parent_id: string | null;
    position: number;
    item_number: string | null;
    title: string;
    description?: string | null;
    category: string;
    status: string;
    document_id: string | null;
    document: {
        slug: string;
        title: string;
        can_preview?: boolean;
        preview_url?: string | null;
    } | null;
    voting_open?: boolean;
    reading_number?: number | null;
    can_second_reading?: boolean;
    can_third_reading?: boolean;
    placed_on_third_reading?: boolean;
    can_postpone?: boolean;
    can_undo?: boolean;
    carried_to?: { id: string; session_number: string; title: string } | null;
    minutes_corrections?: import('@/components/session/MinutesCorrectionsPanel').MinutesCorrectionRow[];
};

type SessionDetail = {
    id: string;
    session_number: string;
    title: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    venue: string | null;
    scheduled_start_at: string | null;
    presiding_officer: string | null;
    secretary: string | null;
    advance_blocked_reason?: string | null;
    agenda_items: AgendaItem[];
};

type Props = {
    session: SessionDetail;
    quorum: QuorumSummary;
    agenda_documents?: {
        id: string;
        title: string;
        reference_number: string | null;
        status?: string;
        document_type?: string;
        current_reading?: number | null;
    }[];
    calendar_docket?: CalendarDocketItem[];
    minutes_consideration?: MinutesConsideration;
    can: Record<string, boolean>;
};

type WorkflowStep = {
    route: string;
    /** A page to open. Status changes stay on `route` and post instead. */
    href?: string;
    labelKey: string;
    icon: LucideIcon;
    variant: 'primary' | 'secondary' | 'live';
    group: 'prepare' | 'chamber';
    disabled?: boolean;
    disabledReason?: string;
};

/** Named so the card reads like the console's Session controls. */
const GROUP_LABEL: Record<WorkflowStep['group'], string> = {
    prepare: 'sessions.workflow_prepare',
    chamber: 'sessions.workflow_chamber',
};

/** The sitting's lifecycle as the stepper draws it; sub-states fold into their stage. */
const STAGES = ['draft', 'agenda-prepared', 'scheduled', 'in-session', 'adjourned', 'finalized'] as const;

const STAGE_OF: Record<string, (typeof STAGES)[number]> = {
    'documents-distributed': 'scheduled',
    suspended: 'in-session',
    'minutes-for-review': 'adjourned',
    archived: 'finalized',
};

const STAGE_COPY: Record<string, { icon: LucideIcon; title: string; hint: string }> = {
    draft: { icon: ClipboardList, title: 'sessions.stage_next_draft', hint: 'sessions.stage_next_draft_hint' },
    'agenda-prepared': {
        icon: CalendarClock,
        title: 'sessions.stage_next_agenda_prepared',
        hint: 'sessions.stage_next_agenda_prepared_hint',
    },
    scheduled: { icon: Play, title: 'sessions.stage_next_scheduled', hint: 'sessions.stage_next_scheduled_hint' },
    'in-session': { icon: Gavel, title: 'sessions.stage_next_in_session', hint: 'sessions.stage_next_in_session_hint' },
    suspended: { icon: Pause, title: 'sessions.stage_next_suspended', hint: 'sessions.stage_next_suspended_hint' },
};

export default function SessionsShow({
    session,
    quorum,
    agenda_documents = [],
    calendar_docket = [],
    minutes_consideration,
    can,
}: Props) {
    const { t } = useTranslations();
    const { formatDateTime } = useFormatters();
    const { auth } = usePage<PageProps>().props;
    const [pending, setPending] = useState<string | null>(null);

    const isLive = toneForState(session.status) === 'live';
    const agendaEditable =
        Boolean(can.manage_agenda) && !['adjourned', 'minutes-for-review', 'finalized', 'archived'].includes(session.status);

    const steps = useMemo(
        () =>
            workflowSteps(session.status, can, {
                floor: preferredFloorPath(session.id, auth.user),
                dashboard: floorPath(session.id, 'dashboard'),
            }),
        [session.status, can, session.id, auth.user],
    );

    function transition(routeName: string) {
        setPending(routeName);
        router.post(
            `/sessions/${session.id}/${routeName}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setPending(null),
            },
        );
    }

    const scheduled = formatDateTime(session.scheduled_start_at);
    const displayTitle = sessionDisplayTitle(session.title) ?? session.title;

    const facts: HeroFact[] = [
        {
            key: 'type',
            icon: ScrollText,
            label: t('sessions.type'),
            value: session.type_label,
        },
        {
            key: 'presiding',
            icon: Gavel,
            label: t('sessions.presiding_officer'),
            value: session.presiding_officer ?? t('sessions.none'),
            muted: !session.presiding_officer,
        },
        {
            key: 'scheduled',
            icon: CalendarClock,
            label: t('sessions.scheduled'),
            value: session.scheduled_start_at ? scheduled : t('sessions.unscheduled'),
            muted: !session.scheduled_start_at,
            mono: true,
        },
        {
            key: 'secretary',
            icon: UserRound,
            label: t('sessions.secretary'),
            value: session.secretary ?? EMPTY_VALUE,
            muted: !session.secretary,
        },
    ];

    return (
        <AppLayout title={session.title}>
            <div className="mx-auto flex max-w-6xl flex-col gap-4">
                <section className="relative overflow-hidden rounded-[var(--radius-lg)] bg-floor-plate px-5 py-5 shadow-[var(--shadow-md)] md:px-6 md:py-6">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span className="text-eyebrow text-floor-ink-faint">{t('sessions.title')}</span>
                                <span aria-hidden="true" className="text-floor-ink-faint">
                                    ·
                                </span>
                                <span className="inline-flex items-center gap-1 font-mono text-xs font-medium tracking-[-0.01em] text-floor-ink-muted">
                                    <Hash aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                    <span className="sr-only">{t('sessions.number')}</span>
                                    {session.session_number}
                                </span>
                            </p>

                            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
                                <h1 className="text-2xl font-semibold tracking-[-0.02em] text-floor-ink md:text-[1.75rem]">
                                    {displayTitle}
                                </h1>
                                <span
                                    className={cn(
                                        'inline-flex shrink-0 items-center gap-1.5 rounded-full border border-floor-line bg-floor-sunk px-2.5 py-1 text-2xs font-semibold tracking-wide uppercase',
                                        isLive ? 'text-floor-ink' : 'text-floor-ink-muted',
                                    )}
                                >
                                    {isLive ? <LiveDot /> : null}
                                    {session.status_label}
                                </span>
                            </div>

                            <p className="mt-1 text-sm text-floor-ink-muted">{session.venue ?? session.type_label}</p>
                        </div>

                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                            {can.update ? (
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    asChild
                                    className="border-floor-line bg-floor-sunk text-floor-ink hover:bg-floor-sunk hover:text-floor-ink"
                                >
                                    <Link href={`/sessions/${session.id}/edit`}>
                                        <Pencil aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                        {t('sessions.edit')}
                                    </Link>
                                </Button>
                            ) : null}
                            <Button
                                variant="secondary"
                                size="sm"
                                asChild
                                className="border-floor-line bg-floor-sunk text-floor-ink hover:bg-floor-sunk hover:text-floor-ink"
                            >
                                <Link href="/sessions">
                                    <ArrowLeft aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                    {t('sessions.back_to_register')}
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div className="mt-5 grid overflow-hidden rounded-[var(--radius-md)] border border-floor-line bg-floor-sunk lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                        <QuorumCard
                            quorum={quorum}
                            tone="plate"
                            className="border-b border-floor-line lg:border-r lg:border-b-0"
                        />
                        <dl className="grid sm:grid-cols-2">
                            {facts.map((fact) => {
                                const Icon = fact.icon;

                                return (
                                    <div
                                        key={fact.key}
                                        className="flex min-w-0 flex-col justify-center border-floor-line px-4 py-3 not-last:border-b sm:odd:border-r sm:[&:nth-last-child(-n+2)]:border-b-0"
                                    >
                                        <dt className="flex items-center gap-1.5 text-2xs font-semibold tracking-[0.06em] text-floor-ink-faint uppercase">
                                            <Icon aria-hidden="true" strokeWidth={1.75} className="size-3.5 shrink-0" />
                                            <span className="truncate">{fact.label}</span>
                                        </dt>
                                        <dd
                                            className={cn(
                                                'mt-1.5 truncate text-md font-medium',
                                                fact.mono && 'font-mono tracking-[-0.02em]',
                                                fact.muted ? 'text-floor-ink-faint' : 'text-floor-ink',
                                            )}
                                        >
                                            {fact.value}
                                        </dd>
                                    </div>
                                );
                            })}
                        </dl>
                    </div>
                </section>

                {steps.length > 0 ? (
                    <WorkflowCard status={session.status} steps={steps} pending={pending} onTransition={transition} />
                ) : null}

                <Panel>
                    <PanelHead sunk>
                        <PanelTitle>{t('sessions.agenda')}</PanelTitle>
                        <div className="flex flex-wrap items-center gap-3">
                            <p className="font-mono text-xs text-ink-faint">
                                {t('sessions.agenda_count', { count: session.agenda_items.length })}
                            </p>
                            {agendaEditable && session.agenda_items.length > 0 ? (
                                <AgendaBuilderToolbar
                                    sessionId={session.id}
                                    items={session.agenda_items}
                                    documents={agenda_documents}
                                />
                            ) : null}
                        </div>
                    </PanelHead>
                    {session.agenda_items.length === 0 ? (
                        <EmptyState
                            bare
                            icon={ListOrdered}
                            title={t('sessions.agenda_empty')}
                            description={t('sessions.agenda_empty_hint')}
                            action={
                                agendaEditable ? (
                                    <AgendaBuilderToolbar
                                        sessionId={session.id}
                                        items={session.agenda_items}
                                        documents={agenda_documents}
                                    />
                                ) : undefined
                            }
                        />
                    ) : (
                        <AgendaList
                            sessionId={session.id}
                            items={session.agenda_items}
                            documents={agenda_documents}
                            editable={agendaEditable}
                            minutesConsideration={minutes_consideration ?? null}
                            applyCorrections={
                                Boolean(can.manage_agenda) &&
                                ['adjourned', 'minutes-for-review', 'finalized', 'archived'].includes(session.status)
                            }
                        />
                    )}
                </Panel>

                <CalendarDocket sessionId={session.id} items={calendar_docket} />
            </div>
        </AppLayout>
    );
}

type HeroFact = {
    key: string;
    icon: LucideIcon;
    label: string;
    value: string;
    /** Reference numbers align down a column, so they stay in the mono. */
    mono?: boolean;
    muted?: boolean;
};

/**
 * The next lawful move, stated as a sentence with its actions beside it, over
 * a stepper showing where the sitting stands in its lifecycle.
 */
function WorkflowCard({
    status,
    steps,
    pending,
    onTransition,
}: {
    status: string;
    steps: WorkflowStep[];
    pending: string | null;
    onTransition: (route: string) => void;
}) {
    const { t } = useTranslations();
    const stage = STAGE_OF[status] ?? status;
    const currentIndex = STAGES.indexOf(stage as (typeof STAGES)[number]);
    const copy = STAGE_COPY[status] ?? STAGE_COPY[stage];
    const Icon = copy?.icon ?? ListOrdered;
    const live = status === 'in-session';
    const blocked = steps.find((step) => step.disabled && step.disabledReason);

    return (
        <section
            aria-label={t('sessions.workflow')}
            className="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface shadow-[var(--shadow-xs)]"
        >
            <div className="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-center md:justify-between md:gap-6">
                <div className="flex min-w-0 items-start gap-3.5">
                    <span
                        aria-hidden="true"
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-[var(--radius-md)] border',
                            live
                                ? 'border-[var(--color-live-line)] bg-live-soft text-live'
                                : 'border-accent-line bg-accent-soft text-accent',
                        )}
                    >
                        <Icon strokeWidth={1.75} className="size-5" />
                    </span>
                    <div className="min-w-0">
                        <p className="label-eyebrow flex items-center gap-1.5">
                            {live ? <LiveDot /> : null}
                            {t('sessions.next_step')}
                            <span aria-hidden="true" className="text-ink-faint">
                                ·
                            </span>
                            {t(GROUP_LABEL[steps[0]?.group ?? 'prepare'])}
                        </p>
                        {copy ? (
                            <>
                                <h2 className="mt-1 text-md font-semibold tracking-[-0.01em] text-ink">{t(copy.title)}</h2>
                                <p className="mt-0.5 text-sm text-ink-muted">{t(copy.hint)}</p>
                            </>
                        ) : null}
                    </div>
                </div>

                <div className="flex shrink-0 flex-col gap-2 md:items-end">
                    <div className="flex flex-wrap items-center gap-2 md:justify-end">
                        {steps.map((step) => {
                            const StepIcon = step.icon;
                            const busy = pending === step.route;

                            if (step.href) {
                                return (
                                    <Button key={step.route} variant={step.variant} asChild>
                                        <Link href={step.href}>
                                            <StepIcon aria-hidden="true" strokeWidth={1.75} />
                                            {t(step.labelKey)}
                                        </Link>
                                    </Button>
                                );
                            }

                            return (
                                <Button
                                    key={step.route}
                                    variant={step.variant}
                                    disabled={pending !== null || Boolean(step.disabled)}
                                    aria-busy={busy || undefined}
                                    title={
                                        step.disabled ? t(step.disabledReason ?? 'sessions.advance_blocked_voting') : undefined
                                    }
                                    onClick={() => onTransition(step.route)}
                                >
                                    {busy ? (
                                        <Loader2 aria-hidden="true" strokeWidth={2} className="animate-spin" />
                                    ) : (
                                        <StepIcon aria-hidden="true" strokeWidth={1.75} />
                                    )}
                                    {t(step.labelKey)}
                                </Button>
                            );
                        })}
                    </div>
                    {blocked ? <p className="text-xs text-ink-muted md:text-right">{t(blocked.disabledReason ?? '')}</p> : null}
                </div>
            </div>

            {currentIndex >= 0 ? (
                <div className="border-t border-line bg-surface-alt px-5 py-3.5">
                    <ol aria-label={t('sessions.lifecycle')} className="flex items-start">
                        {STAGES.map((key, index) => {
                            const done = index < currentIndex;
                            const current = index === currentIndex;

                            return (
                                <li
                                    key={key}
                                    aria-current={current ? 'step' : undefined}
                                    className="relative flex min-w-0 flex-1 flex-col items-center gap-1.5 text-center"
                                >
                                    {index > 0 ? (
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'absolute top-3 right-1/2 h-0.5 w-full -translate-y-1/2 rounded-full',
                                                index <= currentIndex ? 'bg-accent' : 'bg-line',
                                            )}
                                        />
                                    ) : null}
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'relative z-10 flex size-6 items-center justify-center rounded-full border font-mono text-2xs font-semibold',
                                            done && 'border-accent bg-accent text-[var(--color-accent-on)]',
                                            current &&
                                                (live
                                                    ? 'border-live bg-live text-[var(--color-live-on)] ring-4 ring-live-soft'
                                                    : 'border-accent bg-surface text-accent ring-4 ring-accent-soft'),
                                            !done && !current && 'border-line-control bg-surface text-ink-faint',
                                        )}
                                    >
                                        {done ? <Check strokeWidth={2.5} className="size-3.5" /> : index + 1}
                                    </span>
                                    <span
                                        className={cn(
                                            'max-w-full truncate px-1 text-2xs font-medium',
                                            current ? 'text-ink' : done ? 'text-ink-muted' : 'hidden text-ink-faint sm:block',
                                            done && 'hidden sm:block',
                                        )}
                                    >
                                        {t(`sessions.stage_${key.replace(/-/g, '_')}`)}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                </div>
            ) : null}
        </section>
    );
}

function workflowSteps(
    status: string,
    can: Record<string, boolean>,
    chamberLinks: { floor: string; dashboard: string },
): WorkflowStep[] {
    const steps: WorkflowStep[] = [];

    if (status === 'draft' && can.prepare_agenda) {
        steps.push({
            route: 'prepare-agenda',
            labelKey: 'sessions.action_prepare_agenda',
            icon: ClipboardCheck,
            variant: 'primary',
            group: 'prepare',
        });
    }

    if (status === 'agenda-prepared' && can.schedule) {
        steps.push({
            route: 'schedule',
            labelKey: 'sessions.action_schedule',
            icon: CalendarCheck,
            variant: 'primary',
            group: 'prepare',
        });
    }

    if ((status === 'scheduled' || status === 'documents-distributed') && can.start) {
        steps.push({ route: 'start', labelKey: 'sessions.action_start', icon: Play, variant: 'primary', group: 'chamber' });
    }

    if (status === 'suspended' && can.resume) {
        steps.push({ route: 'resume', labelKey: 'sessions.action_resume', icon: Play, variant: 'primary', group: 'chamber' });
    }

    if (status === 'in-session' || status === 'suspended') {
        steps.push(
            {
                route: 'paperless',
                href: chamberLinks.floor,
                labelKey: 'sessions.open_floor',
                icon: Monitor,
                variant: status === 'in-session' ? 'primary' : 'secondary',
                group: 'chamber',
            },
            {
                route: 'dashboard',
                href: chamberLinks.dashboard,
                labelKey: 'sessions.floor.dashboard',
                icon: LayoutDashboard,
                variant: 'secondary',
                group: 'chamber',
            },
        );
    }

    return steps;
}
