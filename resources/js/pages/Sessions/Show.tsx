import { AgendaBuilderToolbar, AgendaList } from '@/components/session/AgendaBuilder';
import { CalendarDocket } from '@/components/session/CalendarDocket';
import type { CalendarDocketItem } from '@/pages/Sessions/Floor/shared';
import { QuorumMeter } from '@/components/session/QuorumMeter';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { LiveDot, toneForState } from '@/components/ui/status';
import { Toolbar, ToolbarGroup } from '@/components/ui/toolbar';
import { SimpleSelect } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout';
import { EMPTY_VALUE, useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { floorPath, preferredFloorPath, sessionDisplayTitle } from '@/lib/sessionFloor';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowLeft, CalendarClock, Gavel, Hash, ListOrdered, ScrollText, UserRound } from 'lucide-react';
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
};

type SessionDetail = {
    id: string;
    session_number: string;
    title: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    recording_enabled?: boolean;
    capture_mode?: 'mixer_mix' | 'per_seat';
    venue: string | null;
    scheduled_start_at: string | null;
    presiding_officer: string | null;
    secretary: string | null;
    advance_blocked_reason?: string | null;
    agenda_items: AgendaItem[];
};

type Quorum = {
    seated_count: number;
    present_count: number;
    required: number;
    met: boolean;
};

type Props = {
    session: SessionDetail;
    quorum: Quorum;
    agenda_documents?: {
        id: string;
        title: string;
        reference_number: string | null;
        status?: string;
        document_type?: string;
        current_reading?: number | null;
    }[];
    calendar_docket?: CalendarDocketItem[];
    can: Record<string, boolean>;
};

type WorkflowStep = {
    route: string;
    labelKey: string;
    variant: 'primary' | 'secondary' | 'live';
    group: 'prepare' | 'chamber';
    disabled?: boolean;
    disabledReason?: string;
};

/** Named so the toolbar reads like the console's Session controls. */
const GROUP_LABEL: Record<WorkflowStep['group'], string> = {
    prepare: 'sessions.workflow_prepare',
    chamber: 'sessions.workflow_chamber',
};

export default function SessionsShow({ session, quorum, agenda_documents = [], calendar_docket = [], can }: Props) {
    const { t } = useTranslations();
    const { formatDateTime } = useFormatters();
    const { auth } = usePage<PageProps>().props;
    const [pending, setPending] = useState<string | null>(null);

    const isLive = toneForState(session.status) === 'live';
    const inChamber = session.status === 'in-session' || session.status === 'suspended';
    const floorHref = preferredFloorPath(session.id, auth.user);
    const agendaEditable =
        Boolean(can.manage_agenda) && !['adjourned', 'minutes-for-review', 'finalized', 'archived'].includes(session.status);

    const votingOpen = session.agenda_items.some((item) => item.status === 'in-progress' && item.voting_open);
    const advanceBlocked = Boolean(session.advance_blocked_reason) || votingOpen;
    const steps = useMemo(
        () => workflowSteps(session.status, can, advanceBlocked, session.advance_blocked_reason),
        [session.status, can, advanceBlocked, session.advance_blocked_reason],
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
            key: 'number',
            icon: Hash,
            label: t('sessions.number'),
            value: session.session_number,
            mono: true,
        },
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
    ];

    const destinations: Destination[] = [
        ...(inChamber
            ? [
                  { href: floorHref, label: t('sessions.open_floor'), emphasis: true },
                  { href: floorPath(session.id, 'dashboard'), label: t('sessions.floor.dashboard') },
              ]
            : []),
        ...(can.update ? [{ href: `/sessions/${session.id}/edit`, label: t('sessions.edit') }] : []),
        { href: `/sessions/${session.id}/attendance`, label: t('sessions.attendance') },
        ...(can.view_transcript ? [{ href: `/sessions/${session.id}/transcript`, label: t('transcripts.live') }] : []),
    ];

    return (
        <AppLayout title={session.title}>
            <div className="mx-auto flex max-w-6xl flex-col gap-4">
                <section className="relative overflow-hidden rounded-[var(--radius-lg)] bg-floor-plate px-5 py-5 shadow-[var(--shadow-md)] md:px-6 md:py-6">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <span className="text-eyebrow text-floor-ink-faint">{t('sessions.title')}</span>

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

                        <Button
                            variant="secondary"
                            size="sm"
                            asChild
                            className="shrink-0 border-floor-line bg-floor-sunk text-floor-ink hover:bg-floor-sunk hover:text-floor-ink"
                        >
                            <Link href="/sessions">
                                <ArrowLeft aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                {t('sessions.back_to_register')}
                            </Link>
                        </Button>
                    </div>

                    <dl className="mt-5 grid divide-y divide-floor-line overflow-hidden rounded-[var(--radius-md)] border border-floor-line bg-floor-sunk sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        {facts.map((fact) => {
                            const Icon = fact.icon;

                            return (
                                <div key={fact.key} className="min-w-0 px-4 py-3">
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
                </section>

                <nav
                    aria-label={t('sessions.views')}
                    className="inline-flex max-w-full flex-wrap items-center gap-1 self-start rounded-full border border-line bg-canvas-sunk p-1"
                >
                    {destinations.map((destination) => (
                        <Link
                            key={destination.href}
                            href={destination.href}
                            className={cn(
                                'inline-flex min-h-9 items-center rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors duration-[var(--duration-fast)]',
                                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-focus)]',
                                destination.emphasis
                                    ? 'bg-accent text-[var(--color-accent-on)] shadow-[var(--shadow-xs)] hover:bg-[var(--color-accent-hover)]'
                                    : 'text-ink-muted hover:bg-surface hover:text-ink',
                            )}
                        >
                            {destination.label}
                        </Link>
                    ))}
                </nav>

                {steps.length > 0 ? (
                    <Toolbar label={t('sessions.workflow')}>
                        {(['prepare', 'chamber'] as const).map((group) => {
                            const grouped = steps.filter((step) => step.group === group);

                            if (grouped.length === 0) {
                                return null;
                            }

                            return (
                                <ToolbarGroup key={group} label={t(GROUP_LABEL[group])}>
                                    {grouped.map((step) => (
                                        <Button
                                            key={step.route}
                                            size="sm"
                                            variant={step.variant}
                                            disabled={pending !== null || Boolean(step.disabled)}
                                            title={step.disabled ? t(step.disabledReason ?? 'sessions.advance_blocked_voting') : undefined}
                                            onClick={() => transition(step.route)}
                                        >
                                            {t(step.labelKey)}
                                        </Button>
                                    ))}
                                </ToolbarGroup>
                            );
                        })}
                    </Toolbar>
                ) : null}

                {can.manage_recording ? (
                    <Toolbar label={t('chamber.recording_controls')}>
                        <ToolbarGroup label={t('chamber.recording')}>
                            <SimpleSelect
                                value={session.capture_mode ?? 'mixer_mix'}
                                onValueChange={(value) => {
                                    if (!value || value === (session.capture_mode ?? 'mixer_mix')) {
                                        return;
                                    }

                                    setPending('recording');
                                    router.post(
                                        `/sessions/${session.id}/recording`,
                                        { capture_mode: value },
                                        {
                                            preserveScroll: true,
                                            onFinish: () => setPending(null),
                                        },
                                    );
                                }}
                                items={[
                                    { value: 'mixer_mix', label: t('chamber.feed.mixer_mix') },
                                    { value: 'per_seat', label: t('chamber.feed.per_seat') },
                                ]}
                                disabled={pending !== null}
                                className="h-8 min-w-[12rem] text-xs"
                            />
                            <Button
                                size="sm"
                                variant={session.recording_enabled ? 'secondary' : 'primary'}
                                disabled={pending !== null}
                                onClick={() => {
                                    setPending('recording');
                                    router.post(
                                        `/sessions/${session.id}/recording`,
                                        { recording_enabled: !session.recording_enabled },
                                        {
                                            preserveScroll: true,
                                            onFinish: () => setPending(null),
                                        },
                                    );
                                }}
                            >
                                {session.recording_enabled
                                    ? t('chamber.disable_recording')
                                    : t('chamber.enable_recording')}
                            </Button>
                        </ToolbarGroup>
                    </Toolbar>
                ) : null}

                <Panel raised={isLive}>
                    <PanelBody className="p-0">
                        <dl className="grid sm:grid-cols-3">
                            <div className="min-w-0 px-5 py-4">
                                <dt className="label-eyebrow">{t('sessions.quorum')}</dt>
                                <dd className="mt-1.5">
                                    <QuorumMeter
                                        presentCount={quorum.present_count}
                                        seatedCount={quorum.seated_count}
                                        required={quorum.required}
                                        met={quorum.met}
                                        markers={false}
                                    />
                                </dd>
                            </div>
                            <StatBlock
                                icon={CalendarClock}
                                label={t('sessions.scheduled')}
                                value={session.scheduled_start_at ? scheduled : t('sessions.unscheduled')}
                                muted={!session.scheduled_start_at}
                                mono
                            />
                            <StatBlock
                                icon={UserRound}
                                label={t('sessions.secretary')}
                                value={session.secretary ?? EMPTY_VALUE}
                                muted={!session.secretary}
                            />
                        </dl>
                    </PanelBody>
                </Panel>

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

type Destination = {
    href: string;
    label: string;
    /** The one chamber destination that wears the national blue. */
    emphasis?: boolean;
};

/** Label above, value below — the stat block the chamber plate uses. */
function StatBlock({
    icon: Icon,
    label,
    value,
    mono = false,
    muted = false,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    mono?: boolean;
    muted?: boolean;
}) {
    return (
        <div className="min-w-0 border-t border-line px-5 py-4 sm:border-t-0 sm:border-l">
            <dt className="label-eyebrow flex items-center gap-1.5">
                <Icon aria-hidden="true" strokeWidth={1.75} className="size-3 shrink-0 text-ink-faint" />
                <span className="truncate">{label}</span>
            </dt>
            <dd
                className={cn(
                    'mt-1.5 truncate text-md font-medium',
                    mono && 'font-mono tracking-[-0.02em]',
                    muted ? 'text-ink-faint' : 'text-ink',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function workflowSteps(
    status: string,
    can: Record<string, boolean>,
    advanceBlocked = false,
    advanceBlockedReason?: string | null,
): WorkflowStep[] {
    const steps: WorkflowStep[] = [];

    if (status === 'draft' && can.prepare_agenda) {
        steps.push({
            route: 'prepare-agenda',
            labelKey: 'sessions.action_prepare_agenda',
            variant: 'primary',
            group: 'prepare',
        });
    }

    if (status === 'agenda-prepared' && can.schedule) {
        steps.push({ route: 'schedule', labelKey: 'sessions.action_schedule', variant: 'primary', group: 'prepare' });
    }

    if ((status === 'scheduled' || status === 'documents-distributed') && can.start) {
        steps.push({ route: 'start', labelKey: 'sessions.action_start', variant: 'primary', group: 'chamber' });
    }

    if (status === 'in-session' && can.manage_agenda) {
        steps.push({
            route: 'agenda/advance',
            labelKey: 'sessions.action_next_item',
            variant: 'secondary',
            group: 'chamber',
            disabled: advanceBlocked,
            disabledReason: advanceBlockedReason ?? 'sessions.advance_blocked_voting',
        });
    }

    if (status === 'in-session' && can.suspend) {
        steps.push({ route: 'suspend', labelKey: 'sessions.action_suspend', variant: 'secondary', group: 'chamber' });
    }

    if (status === 'suspended' && can.resume) {
        steps.push({ route: 'resume', labelKey: 'sessions.action_resume', variant: 'primary', group: 'chamber' });
    }

    if ((status === 'in-session' || status === 'suspended') && can.adjourn) {
        steps.push({ route: 'adjourn', labelKey: 'sessions.action_adjourn', variant: 'live', group: 'chamber' });
    }

    return steps;
}
