import { AiContent } from '@/components/ai/AiContent';
import { ReferToCommitteeDialog } from '@/components/documents/ReferToCommitteeDialog';
import { CommitteeReportBody } from '@/components/documents/CommitteeReportBody';
import { DocumentPdfViewer } from '@/components/documents/DocumentPdfViewer';
import { ViewReportDialog } from '@/components/documents/ViewReportDialog';
import { VOTE_BAR } from '@/components/session/VoteBoard';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Panel, PanelBody, PanelFoot, PanelHead, PanelTitle } from '@/components/ui/panel';
import { LiveDot, StatusChip } from '@/components/ui/status';
import { SimpleSelect } from '@/components/ui/select';
import { Notice } from '@/components/ui/notice';
import { Field, fieldAria } from '@/components/ui/field';
import { Checkbox, Input } from '@/components/ui/input';
import type { TranscriptSegment } from '@/lib/echo';
import { useFormatters } from '@/lib/format';
import { sessionDisplayTitle, withHonorific } from '@/lib/sessionFloor';
import { isLowConfidence, useLowConfidenceThreshold } from '@/lib/transcriptConfidence';
import { isSegmentAttributed, segmentSpeakerLabel } from '@/lib/transcriptSpeaker';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { CircleStop, Clock3, Eye, EyeOff, FileText, Gavel, History, Mic, Pencil, Scale, ScrollText, Users, Vote } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
    floorControlModel,
    formatAgendaNumber,
    formatClockCountdown,
    formatClockElapsed,
    isTimedRecess,
    isUnreferredFirstReading,
    referredCommitteeNames,
    useRecessRemaining,
    type AgendaItem,
    type FloorControl,
    type FloorProps,
    type ReadingPackItem,
    type Translate,
    type VotingState,
} from './shared';

/**
 * The console kit — the working surfaces where the record is changed at the
 * secretariat's desk. Unlike the chamber dashboard, which is read across a
 * room, these are operated at arm's length: the sitting's identity and its
 * three counted facts sit on one plate at the top, the actions that change
 * the record run down the left — session controls sit high on that column,
 * before the docket and hall PDF — and the order of business runs down the
 * right where it can be watched without being acted on.
 */

/* -------------------------------------------------------------------------- */
/* The plate                                                                   */
/* -------------------------------------------------------------------------- */

type PlateStat = {
    key: string;
    icon: typeof Users;
    label: string;
    value: string;
    caption: string;
};

export function ConsolePlate({
    session,
    role,
    quorum,
    elapsedSeconds,
    voting,
    expectedBallots,
    t,
}: {
    session: FloorProps['session'];
    /** Which desk is reading this plate — the eyebrow above the sitting's title. */
    role: string;
    quorum: FloorProps['quorum'];
    elapsedSeconds: number | null;
    voting: VotingState;
    expectedBallots: number;
    t: Translate;
}) {
    const live = session.status === 'in-session';
    const title = sessionDisplayTitle(session.title);
    const officer = withHonorific(session.presiding_officer);
    const recessed = isTimedRecess(session);
    const remaining = useRecessRemaining(recessed ? (session.recess_remaining_seconds ?? 0) : null);
    const recessEnded = remaining === 0;

    const meta = [session.venue, officer ? t('sessions.presided_by', { name: officer }) : null]
        .filter(Boolean)
        .join(' · ');

    const stats: PlateStat[] = [
        {
            key: 'quorum',
            icon: Users,
            label: t('sessions.quorum'),
            value: `${quorum.present_count}/${quorum.required}`,
            caption: quorum.met ? t('sessions.quorum_met') : t('sessions.quorum_not_met'),
        },
        recessed
            ? {
                  key: 'recess',
                  icon: Clock3,
                  label: t('sessions.recess_remaining'),
                  value: recessEnded ? t('sessions.recess_ended') : formatClockCountdown(remaining),
                  caption: recessEnded ? t('sessions.recess_ended_hint') : t('sessions.recess_until_resume'),
              }
            : {
                  key: 'elapsed',
                  icon: Clock3,
                  label: t('sessions.elapsed'),
                  value: formatClockElapsed(elapsedSeconds),
                  caption: t('sessions.since_call_to_order'),
              },
        {
            key: 'ballots',
            icon: Vote,
            label: t('sessions.ballots'),
            value: `${voting.tallies.total}/${expectedBallots || quorum.present_count}`,
            caption: voting.open ? t('sessions.voting_open_label') : t('sessions.voting_idle'),
        },
    ];

    return (
        <section className="relative overflow-hidden rounded-[var(--radius-lg)] bg-floor-plate px-5 py-5 shadow-[var(--shadow-md)] md:px-6 md:py-6">
            <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-eyebrow text-floor-ink-faint">{role}</span>
                        {live ? (
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-floor-line bg-floor-sunk px-2 py-0.5 text-2xs font-semibold tracking-wide text-floor-ink uppercase">
                                <LiveDot />
                                {t('sessions.floor.live')}
                            </span>
                        ) : recessed ? (
                            <span className="text-2xs font-semibold tracking-wide text-floor-ink-muted uppercase">
                                {recessEnded ? t('sessions.recess_ended') : t('sessions.action_recess')}
                            </span>
                        ) : (
                            <span className="text-2xs font-semibold tracking-wide text-floor-ink-muted uppercase">
                                {session.status_label}
                            </span>
                        )}
                    </div>

                    {title ? (
                        <h1 className="mt-2 text-2xl font-semibold tracking-[-0.02em] text-floor-ink md:text-[1.75rem]">
                            {title}
                        </h1>
                    ) : null}

                    {meta ? <p className="mt-1 text-sm text-floor-ink-muted">{meta}</p> : null}
                </div>
            </div>

            <dl className="mt-5 grid divide-y divide-floor-line overflow-hidden rounded-[var(--radius-md)] border border-floor-line bg-floor-sunk sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                {stats.map((stat) => {
                    const Icon = stat.icon;

                    return (
                        <div key={stat.key} className="px-4 py-3.5">
                            <dt className="flex items-center gap-1.5 text-2xs font-semibold tracking-[0.06em] text-floor-ink-faint uppercase">
                                <Icon aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                {stat.label}
                            </dt>
                            <dd className="mt-1.5 font-mono text-figure-sm font-medium tracking-[-0.03em] text-floor-ink">
                                {stat.value}
                            </dd>
                            <dd className="mt-1 text-xs text-floor-ink-muted">{stat.caption}</dd>
                        </div>
                    );
                })}
            </dl>
        </section>
    );
}

/* -------------------------------------------------------------------------- */
/* Current item and the tally strip                                            */
/* -------------------------------------------------------------------------- */

export function ConsoleAgendaCard({
    sessionId,
    item,
    packItem,
    voting,
    hallDisplay,
    canControlHall,
    committees = [],
    actions,
    t,
}: {
    sessionId: string;
    item: AgendaItem | null;
    packItem: ReadingPackItem | null;
    voting: VotingState;
    hallDisplay: FloorProps['hall_display'];
    canControlHall: boolean;
    committees?: { id: string; name: string }[];
    /** Desk-specific actions on the item, struck alongside the hall controls. */
    actions?: ReactNode;
    t: Translate;
}) {
    const document = packItem?.document ?? null;
    const report = packItem?.committee_report ?? item?.committee_report ?? null;
    const canPreview = Boolean(document?.can_preview && document.preview_url);
    const canRefer = packItem?.can_refer === true && document !== null;
    const canEditReferral = packItem?.can_edit_referral === true && document !== null;
    const referredNames = referredCommitteeNames(document);
    const { formatList } = useFormatters();
    const firstReadingReferred =
        packItem?.reading_number === 1 &&
        referredNames.length > 0 &&
        ['committee-referral', 'committee-review', 'committee-report'].includes(document?.status ?? '');
    const [referOpen, setReferOpen] = useState(false);
    const [reportOpen, setReportOpen] = useState(false);
    const projectingThisItem =
        hallDisplay?.agenda_item_id != null &&
        (hallDisplay.agenda_item_id === item?.id || hallDisplay.agenda_item_id === packItem?.id);
    const projectingDocument = hallDisplay?.stage === 'document' && projectingThisItem;
    const projectingReport = hallDisplay?.stage === 'report' && projectingThisItem;
    const projecting = projectingDocument || projectingReport;

    function projectDocument() {
        if (!item || !canPreview) {
            return;
        }

        router.post(
            `/sessions/${sessionId}/hall/document`,
            { agenda_item_id: item.id },
            { preserveScroll: true },
        );
    }

    function projectReport() {
        if (!item || !report) {
            return;
        }

        router.post(
            `/sessions/${sessionId}/hall/report`,
            { agenda_item_id: item.id },
            { preserveScroll: true },
        );
    }

    function hideProjection() {
        router.post(`/sessions/${sessionId}/hall/item`, {}, { preserveScroll: true });
    }

    return (
        <Panel as="section" className="session-item-enter">
            <PanelBody className="py-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-4">
                        {item?.item_number ? (
                            <span className="font-mono text-figure-sm font-medium tracking-[-0.03em] text-ink">
                                {formatAgendaNumber(item.item_number)}
                            </span>
                        ) : null}
                        <div className="min-w-0">
                            <p className="label-eyebrow">{t('sessions.current_item')}</p>
                            <h2 className="mt-1 text-lg font-semibold tracking-[-0.02em] text-ink">
                                {item ? item.title : t('sessions.no_current_item')}
                            </h2>
                            {document ? (
                                <p className="mt-1 flex items-center gap-1.5 text-xs text-ink-muted">
                                    <FileText aria-hidden="true" strokeWidth={1.75} className="size-3.5 shrink-0" />
                                    <span className="truncate">{document.title}</span>
                                </p>
                            ) : null}
                        </div>
                    </div>

                    <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                        {item?.voting_open ? (
                            <StatusChip tone="live" size="sm">
                                {t('sessions.voting_open_label')}
                            </StatusChip>
                        ) : null}
                        {projecting ? (
                            <StatusChip tone="review" size="sm">
                                {t('sessions.hall.on_screen')}
                            </StatusChip>
                        ) : null}
                        {firstReadingReferred ? (
                            <StatusChip tone="review" size="sm">
                                {t('sessions.floor.referred_to', { committee: formatList(referredNames) })}
                            </StatusChip>
                        ) : null}
                    </div>
                </div>

                {(canControlHall && canPreview) || canRefer || canEditReferral || report || actions ? (
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        {actions}
                        {report ? (
                            canControlHall ? (
                                projectingReport ? (
                                    <Button type="button" variant="secondary" size="sm" onClick={hideProjection}>
                                        <EyeOff aria-hidden="true" strokeWidth={1.75} />
                                        {t('sessions.hall.hide_report')}
                                    </Button>
                                ) : (
                                    <Button type="button" variant="secondary" size="sm" onClick={projectReport}>
                                        <ScrollText aria-hidden="true" strokeWidth={1.75} />
                                        {t('documents.view_report')}
                                    </Button>
                                )
                            ) : (
                                <Button type="button" variant="secondary" size="sm" onClick={() => setReportOpen(true)}>
                                    <ScrollText aria-hidden="true" strokeWidth={1.75} />
                                    {t('documents.view_report')}
                                </Button>
                            )
                        ) : null}
                        {canControlHall && canPreview ? (
                            projectingDocument ? (
                                <Button type="button" variant="secondary" size="sm" onClick={hideProjection}>
                                    <EyeOff aria-hidden="true" strokeWidth={1.75} />
                                    {t('sessions.hall.hide_document')}
                                </Button>
                            ) : (
                                <Button type="button" variant="secondary" size="sm" onClick={projectDocument}>
                                    <Eye aria-hidden="true" strokeWidth={1.75} />
                                    {t('sessions.hall.view_document')}
                                </Button>
                            )
                        ) : null}
                        {canRefer && document ? (
                            <Button type="button" variant="secondary" size="sm" onClick={() => setReferOpen(true)}>
                                <Scale aria-hidden="true" strokeWidth={1.75} />
                                {t('documents.refer_title')}
                            </Button>
                        ) : null}
                        {canEditReferral && document ? (
                            <Button type="button" variant="secondary" size="sm" onClick={() => setReferOpen(true)}>
                                <Pencil aria-hidden="true" strokeWidth={1.75} />
                                {t('documents.refer_edit')}
                            </Button>
                        ) : null}
                        {canControlHall && report ? (
                            <span className="text-xs text-ink-muted">{t('sessions.hall.view_report_hint')}</span>
                        ) : canControlHall && canPreview ? (
                            <span className="text-xs text-ink-muted">{t('sessions.hall.view_document_hint')}</span>
                        ) : null}
                    </div>
                ) : null}
            </PanelBody>

            {voting.open ? <TallyStrip tallies={voting.tallies} t={t} /> : null}

            {report ? (
                <ViewReportDialog report={report} open={reportOpen} onOpenChange={setReportOpen} />
            ) : null}

            {document ? (
                <ReferToCommitteeDialog
                    documentSlug={document.slug}
                    committeeId={document.committee_id ?? null}
                    committeeIds={document.open_referral?.committee_ids}
                    meetingOn={document.open_referral?.meeting_on}
                    remarks={document.open_referral?.remarks}
                    committees={committees}
                    open={referOpen}
                    onOpenChange={setReferOpen}
                    action={`/sessions/${sessionId}/floor/refer`}
                    agendaItemId={item?.id ?? packItem?.id}
                    mode={canEditReferral ? 'edit' : 'create'}
                    idPrefix={`floor-refer-${item?.id ?? packItem?.id ?? 'item'}`}
                />
            ) : null}
        </Panel>
    );
}

/**
 * Clerk-side preview of whatever is currently projected to the hall. The board
 * follows the same hall_display state; this pane is so the desk can confirm
 * the file without turning away from the console. Zoom and scroll here drive
 * what the chamber sees.
 */
export function ConsoleHallDocument({
    sessionId,
    packItem,
    view = null,
    t,
}: {
    sessionId: string;
    packItem: ReadingPackItem | null;
    view?: {
        zoom: number;
        page: number;
        relative_x: number;
        relative_y: number;
    } | null;
    t: Translate;
}) {
    const measure = packItem?.document ?? null;
    const canPreview = Boolean(measure?.can_preview && measure.preview_url);

    function publishView(next: import('@/components/documents/DocumentPdfViewer').HallDocumentView) {
        const csrf = window.document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        void fetch(`/sessions/${sessionId}/hall/view`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(next),
        });
    }

    if (!measure || !canPreview) {
        return null;
    }

    return (
        <Panel as="section" className="overflow-hidden">
            <PanelHead className="px-4 py-3">
                <div className="flex min-w-0 items-center gap-2">
                    <FileText aria-hidden="true" strokeWidth={1.75} className="size-4 shrink-0 text-ink-faint" />
                    <PanelTitle>{t('sessions.hall.console_preview')}</PanelTitle>
                </div>
                <StatusChip tone="review" size="sm">
                    {t('sessions.hall.on_screen')}
                </StatusChip>
            </PanelHead>
            <PanelBody className="p-0">
                <div className="h-[min(28rem,50vh)] min-h-[16rem]">
                    <DocumentPdfViewer
                        key={measure.version_id ?? measure.preview_url!}
                        src={measure.preview_url!}
                        presentation
                        syncRole="driver"
                        view={view ?? null}
                        onViewChange={publishView}
                        className="h-full min-h-0 rounded-none border-0"
                    />
                </div>
            </PanelBody>
        </Panel>
    );
}

export function ConsoleHallReport({ packItem, t }: { packItem: ReadingPackItem | null; t: Translate }) {
    const report = packItem?.committee_report ?? null;

    if (!report) {
        return null;
    }

    return (
        <Panel as="section" className="overflow-hidden">
            <PanelHead className="px-4 py-3">
                <div className="flex min-w-0 items-center gap-2">
                    <ScrollText aria-hidden="true" strokeWidth={1.75} className="size-4 shrink-0 text-ink-faint" />
                    <PanelTitle>{t('sessions.hall.console_report')}</PanelTitle>
                </div>
                <StatusChip tone="review" size="sm">
                    {t('sessions.hall.on_screen')}
                </StatusChip>
            </PanelHead>
            <PanelBody className="p-0">
                <div className="h-[min(28rem,50vh)] min-h-[16rem] overflow-y-auto px-5 py-4">
                    <CommitteeReportBody report={report} />
                </div>
            </PanelBody>
        </Panel>
    );
}

function TallyStrip({ tallies, t }: { tallies: VotingState['tallies']; t: Translate }) {
    const columns = [
        { key: 'yes' as const, label: t('sessions.vote_yes'), value: tallies.yes },
        { key: 'no' as const, label: t('sessions.vote_no'), value: tallies.no },
        { key: 'abstain' as const, label: t('sessions.vote_abstain'), value: tallies.abstain },
        { key: 'inhibit' as const, label: t('sessions.vote_inhibit'), value: tallies.inhibit },
    ];

    const base = Math.max(tallies.total, 1);

    return (
        <div
            role="group"
            aria-label={t('sessions.tally_label')}
            className="grid grid-cols-2 gap-px border-t border-line bg-line sm:grid-cols-4"
        >
            {columns.map((column) => (
                <div key={column.key} className="bg-surface px-4 py-3">
                    <p className="font-mono text-figure-sm font-medium tracking-[-0.03em] text-ink">{column.value}</p>
                    <p className="mt-1 text-2xs font-semibold tracking-[0.08em] text-ink-subtle uppercase">
                        {column.label}
                    </p>
                    <div className="mt-2.5 h-1 overflow-hidden rounded-full bg-chart-track" aria-hidden="true">
                        <div
                            className={cn('h-full rounded-full transition-[width] duration-500', VOTE_BAR[column.key])}
                            style={{ width: `${Math.round((column.value / base) * 100)}%` }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/* Session controls                                                            */
/* -------------------------------------------------------------------------- */

function ControlRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-2 border-t border-line px-5 py-3 first:border-t-0 sm:grid-cols-[5.5rem_minmax(0,1fr)] sm:items-center sm:gap-4">
            <span className="label-eyebrow">{label}</span>
            <div className="flex flex-wrap items-center gap-2">{children}</div>
        </div>
    );
}

function ControlButton({
    sessionId,
    control,
    size = 'default',
    disabled = false,
    disabledReason,
    onActivate,
}: {
    sessionId: string;
    control: FloorControl;
    size?: 'default' | 'floor';
    disabled?: boolean;
    disabledReason?: string;
    /** Intercept the post — used when advancing needs a confirmation first. */
    onActivate?: () => void;
}) {
    const Icon = control.icon;

    if (disabled) {
        return (
            <Button variant="secondary" size={size} disabled title={disabledReason}>
                <Icon aria-hidden="true" strokeWidth={1.75} />
                {control.label}
            </Button>
        );
    }

    if (onActivate) {
        return (
            <Button variant="secondary" size={size} onClick={onActivate} aria-haspopup="dialog">
                <Icon aria-hidden="true" strokeWidth={1.75} />
                {control.label}
            </Button>
        );
    }

    return (
        <Button asChild variant="secondary" size={size}>
            <Link href={`/sessions/${sessionId}/${control.route}`} method="post" as="button" preserveScroll>
                <Icon aria-hidden="true" strokeWidth={1.75} />
                {control.label}
            </Link>
        </Button>
    );
}

export function ConsoleControls({
    session,
    currentItem,
    nextItem,
    previousItem = null,
    currentPackItem = null,
    can,
    voting,
    hallDisplay,
    expectedBallots,
    auditHint,
    touch = false,
    t,
    advanceBlockedReason = null,
}: {
    session: FloorProps['session'];
    currentItem: AgendaItem | null;
    nextItem: AgendaItem | null;
    previousItem?: AgendaItem | null;
    currentPackItem?: ReadingPackItem | null;
    can: Record<string, boolean>;
    voting: VotingState;
    hallDisplay?: FloorProps['hall_display'];
    expectedBallots: number;
    /** Whose credentials the log will carry. Defaults to the clerk's wording. */
    auditHint?: string;
    /** Rostrum sizing: struck standing, at arm's length, under a gavel. */
    touch?: boolean;
    t: Translate;
    advanceBlockedReason?: string | null;
}) {
    const currentItemId = currentItem?.id ?? null;
    const agendaAdvanceable =
        Boolean(can.manage_agenda) && (session.status === 'in-session' || session.status === 'suspended');
    const needsReferralConfirm = isUnreferredFirstReading(currentPackItem);
    const [confirmAdvanceOpen, setConfirmAdvanceOpen] = useState(false);
    const [recessOpen, setRecessOpen] = useState(false);
    const [durationMinutes, setDurationMinutes] = useState(10);
    const [recessing, setRecessing] = useState(false);
    const [advancing, setAdvancing] = useState(false);
    const [retreating, setRetreating] = useState(false);
    const [silentVote, setSilentVote] = useState(false);
    const advancingRef = useRef(false);
    const retreatingRef = useRef(false);
    const advanceBlockReason = advanceBlockedReason ?? (voting.open ? 'sessions.advance_blocked_voting' : null);
    const retreatBlockReason = voting.open
        ? 'sessions.retreat_blocked_voting'
        : previousItem
          ? null
          : 'sessions.no_previous_item';
    const { floor, advance, retreat, adjournEnabled, showOpenVoting, showCloseVoting } = floorControlModel({
        sessionStatus: session.status,
        currentItemId,
        previousItemId: previousItem?.id ?? null,
        nextItem,
        can,
        voting,
        t,
        advanceBlockedReason: advanceBlockReason,
    });

    const availableFloor = floor.filter((control) => control.enabled);
    const size = touch ? 'floor' : 'default';
    const agendaBusy = advancing || retreating;

    useEffect(() => {
        setSilentVote(false);
    }, [currentItemId]);

    function postAdvance() {
        if (advancingRef.current || retreatingRef.current) {
            return;
        }

        advancingRef.current = true;
        setAdvancing(true);
        router.post(
            `/sessions/${session.id}/${advance.route}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    advancingRef.current = false;
                    setAdvancing(false);
                    setConfirmAdvanceOpen(false);
                },
            },
        );
    }

    function requestAdvance() {
        if (advancingRef.current || retreatingRef.current) {
            return;
        }

        if (needsReferralConfirm) {
            setConfirmAdvanceOpen(true);

            return;
        }

        postAdvance();
    }

    function postRetreat() {
        if (advancingRef.current || retreatingRef.current) {
            return;
        }

        retreatingRef.current = true;
        setRetreating(true);
        router.post(
            `/sessions/${session.id}/agenda/retreat`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    retreatingRef.current = false;
                    setRetreating(false);
                },
            },
        );
    }

    const canControlHall = Boolean(can.control_hall_display);
    const showingResults = hallDisplay?.stage === 'results';
    const canShowPrevious = canControlHall && Boolean(voting.previous) && !voting.open;

    function postVoting(action: 'open' | 'close') {
        if (!currentItemId) {
            return;
        }

        router.post(
            `/sessions/${session.id}/voting/${action}`,
            {
                agenda_item_id: currentItemId,
                ...(action === 'open' ? { silent: silentVote } : {}),
            },
            { preserveScroll: true },
        );
    }

    function showPreviousResult() {
        if (!currentItemId) {
            return;
        }

        router.post(
            `/sessions/${session.id}/hall/results`,
            { agenda_item_id: currentItemId },
            { preserveScroll: true },
        );
    }

    function hidePreviousResult() {
        router.post(`/sessions/${session.id}/hall/item`, {}, { preserveScroll: true });
    }

    function openRecess() {
        setDurationMinutes(10);
        setRecessOpen(true);
    }

    function postRecess() {
        if (recessing) {
            return;
        }

        setRecessing(true);
        const minutes = Math.min(60, Math.max(1, durationMinutes || 10));
        router.post(
            `/sessions/${session.id}/recess`,
            { duration_minutes: minutes },
            {
                preserveScroll: true,
                onFinish: () => {
                    setRecessing(false);
                    setRecessOpen(false);
                },
            },
        );
    }

    return (
        <>
        <Panel as="section" id="session-controls">
            <PanelHead className="flex-col items-start gap-1">
                <PanelTitle>{t('sessions.session_controls')}</PanelTitle>
                <p className="text-xs text-ink-muted">{auditHint ?? t('sessions.controls_audit_hint')}</p>
            </PanelHead>

            <div>
                <ControlRow label={t('sessions.controls_floor')}>
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-line bg-canvas-sunk px-2.5 py-1 text-xs font-medium text-ink-muted">
                        {session.status === 'in-session' ? <LiveDot /> : null}
                        {session.status_label}
                    </span>
                    {availableFloor.map((control) => (
                        <ControlButton
                            key={control.key}
                            sessionId={session.id}
                            control={control}
                            size={size}
                            onActivate={control.key === 'recess' ? openRecess : undefined}
                        />
                    ))}
                </ControlRow>

                <ControlRow label={t('sessions.controls_agenda')}>
                    {agendaAdvanceable ? (
                        <>
                            <ControlButton
                                sessionId={session.id}
                                control={retreat}
                                size={size}
                                disabled={!retreat.enabled || agendaBusy}
                                disabledReason={
                                    retreat.enabled ? undefined : t(retreatBlockReason ?? 'sessions.no_previous_item')
                                }
                                onActivate={retreat.enabled ? postRetreat : undefined}
                            />
                            <ControlButton
                                sessionId={session.id}
                                control={advance}
                                size={size}
                                disabled={!advance.enabled || agendaBusy}
                                disabledReason={
                                    advance.enabled
                                        ? undefined
                                        : t(advanceBlockReason ?? 'sessions.advance_blocked_voting')
                                }
                                onActivate={advance.enabled ? requestAdvance : undefined}
                            />
                        </>
                    ) : (
                        <span className="text-sm text-ink-muted">{t('sessions.agenda_locked')}</span>
                    )}
                    <span className="text-sm text-ink-muted">
                        {advanceBlockReason && agendaAdvanceable
                            ? t(advanceBlockReason)
                            : [
                                  previousItem
                                      ? t('sessions.previous_named', { title: previousItem.title })
                                      : null,
                                  nextItem
                                      ? t('sessions.next_named', { title: nextItem.title })
                                      : t('sessions.no_next_item'),
                              ]
                                  .filter(Boolean)
                                  .join(' · ')}
                    </span>
                </ControlRow>

                <ControlRow label={t('sessions.controls_vote')}>
                    {can.update_voting_mode ? (
                        <label htmlFor="defer-heading-votes" className="inline-flex items-center gap-2 text-sm text-ink">
                            <Checkbox
                                id="defer-heading-votes"
                                checked={Boolean(session.defer_heading_votes)}
                                onChange={(event) => {
                                    router.post(
                                        `/sessions/${session.id}/voting-mode`,
                                        { defer_heading_votes: event.target.checked },
                                        { preserveScroll: true },
                                    );
                                }}
                            />
                            {t('sessions.discuss_heading_then_vote')}
                        </label>
                    ) : null}
                    {showOpenVoting ? (
                        <>
                            <label htmlFor="silent-vote" className="inline-flex items-center gap-2 text-sm text-ink">
                                <Checkbox
                                    id="silent-vote"
                                    checked={silentVote}
                                    onChange={(event) => setSilentVote(event.target.checked)}
                                />
                                {t('sessions.silent_voting')}
                            </label>
                            <Button variant="live" size={size} onClick={() => postVoting('open')}>
                                <Vote aria-hidden="true" strokeWidth={1.75} />
                                {t('sessions.action_open_voting')}
                            </Button>
                        </>
                    ) : null}
                    {showCloseVoting ? (
                        <Button variant="primary" size={size} onClick={() => postVoting('close')}>
                            <CircleStop aria-hidden="true" strokeWidth={1.75} />
                            {t('sessions.action_close_voting')}
                        </Button>
                    ) : null}
                    {canShowPrevious && !showingResults ? (
                        <Button type="button" variant="secondary" size={size} onClick={showPreviousResult}>
                            <History aria-hidden="true" strokeWidth={1.75} />
                            {t('sessions.hall.view_previous_voting')}
                        </Button>
                    ) : null}
                    {showingResults && canControlHall ? (
                        <Button type="button" variant="secondary" size={size} onClick={hidePreviousResult}>
                            <EyeOff aria-hidden="true" strokeWidth={1.75} />
                            {t('sessions.hall.hide_previous_voting')}
                        </Button>
                    ) : null}
                    {showingResults ? (
                        <StatusChip tone="review" size="sm">
                            {t('sessions.hall.on_screen')}
                        </StatusChip>
                    ) : null}

                    <span className="text-sm text-ink-muted">
                        {voting.open
                            ? t('sessions.ballots_recorded_of', {
                                  count: voting.tallies.total,
                                  of: expectedBallots || voting.tallies.total,
                              })
                            : showingResults
                              ? t('sessions.hall.previous_voting_hint')
                              : t('sessions.voting_unavailable')}
                    </span>
                </ControlRow>
            </div>

            <PanelFoot className="justify-between gap-3">
                <p className="min-w-0 flex-1 text-xs text-ink-muted">{t('sessions.adjourn_hint')}</p>
                <Button asChild={adjournEnabled} disabled={!adjournEnabled} variant="live" size={size}>
                    {adjournEnabled ? (
                        <Link href={`/sessions/${session.id}/adjourn`} method="post" as="button" preserveScroll>
                            <Gavel aria-hidden="true" strokeWidth={1.75} />
                            {t('sessions.action_adjourn_session')}
                        </Link>
                    ) : (
                        <span className="inline-flex items-center gap-1.5">
                            <Gavel aria-hidden="true" strokeWidth={1.75} />
                            {t('sessions.action_adjourn_session')}
                        </span>
                    )}
                </Button>
            </PanelFoot>
        </Panel>

            <Dialog open={confirmAdvanceOpen} onOpenChange={setConfirmAdvanceOpen}>
                <DialogContent
                    title={t('sessions.advance_unreferred_title')}
                    description={t('sessions.advance_unreferred_hint')}
                >
                    <DialogFooter className="mt-0">
                        <Button type="button" variant="ghost" onClick={() => setConfirmAdvanceOpen(false)}>
                            {t('sessions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            disabled={advancing}
                            onClick={postAdvance}
                        >
                            {t('sessions.advance_unreferred_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={recessOpen} onOpenChange={setRecessOpen}>
                <DialogContent
                    title={t('sessions.recess_title')}
                    description={t('sessions.recess_hint')}
                >
                    <Field
                        id="recess-duration"
                        label={t('sessions.recess_duration')}
                        hint={t('sessions.recess_duration_hint')}
                    >
                        <Input
                            {...fieldAria('recess-duration', { hint: t('sessions.recess_duration_hint') })}
                            type="number"
                            min={1}
                            max={60}
                            value={durationMinutes}
                            onChange={(event) => {
                                const next = Number.parseInt(event.target.value, 10);
                                setDurationMinutes(Number.isNaN(next) ? 10 : next);
                            }}
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setRecessOpen(false)}>
                            {t('sessions.cancel')}
                        </Button>
                        <Button type="button" variant="primary" disabled={recessing} onClick={postRecess}>
                            {t('sessions.recess_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/* -------------------------------------------------------------------------- */
/* Right rail                                                                  */
/* -------------------------------------------------------------------------- */

function formatSegmentTime(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);

    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

export function ConsoleTranscript({
    sessionId,
    transcriptId,
    segments,
    status,
    error = null,
    t,
    speakers = [],
    canCorrect = false,
}: {
    sessionId: string;
    transcriptId?: string | null;
    segments: TranscriptSegment[];
    status: string | null;
    error?: string | null;
    t: Translate;
    speakers?: { id: string; display_name: string | null }[];
    canCorrect?: boolean;
}) {
    const threshold = useLowConfidenceThreshold();
    const inbox = canCorrect && Boolean(transcriptId);
    const [pendingIndex, setPendingIndex] = useState<number | null>(null);
    const [choice, setChoice] = useState<Record<number, string>>({});

    const unassigned = useMemo(
        () => segments.filter((segment) => !isSegmentAttributed(segment)),
        [segments],
    );
    const attributed = useMemo(
        () => segments.filter((segment) => isSegmentAttributed(segment)),
        [segments],
    );
    const recent = inbox ? attributed.slice(-8) : attributed.slice(-3);
    const speakerItems = speakers
        .filter((row) => row.id)
        .map((row) => ({
            value: row.id,
            label: row.display_name ?? row.id,
        }));

    async function assign(segment: TranscriptSegment, value: string) {
        if (!transcriptId || !value) {
            return;
        }

        setPendingIndex(segment.index);

        const body =
            value === '__gallery__'
                ? { text: segment.text, gallery: true }
                : { text: segment.text, speaker_id: value };

        try {
            await fetch(`/sessions/${sessionId}/transcript/${transcriptId}/segments/${segment.index}`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(body),
            });
        } finally {
            setPendingIndex(null);
        }
    }

    return (
        <Panel as="section">
            <PanelHead className="px-4 py-3">
                <div className="flex min-w-0 items-center gap-2">
                    <Mic aria-hidden="true" strokeWidth={1.75} className="size-4 text-ink-faint" />
                    <PanelTitle>{inbox ? t('transcripts.discussion_inbox') : t('transcripts.live')}</PanelTitle>
                    <span className="ai-badge">{t('ai.badge')}</span>
                    {status === 'failed' || error ? (
                        <StatusChip tone="blocked" size="sm">
                            {t('transcripts.failed')}
                        </StatusChip>
                    ) : status === 'processing' ? (
                        <StatusChip tone="review" size="sm">
                            {t('transcripts.processing')}
                        </StatusChip>
                    ) : null}
                    {inbox && unassigned.length > 0 ? (
                        <StatusChip tone="review" size="sm">
                            {t('transcripts.unassigned_count', { count: unassigned.length })}
                        </StatusChip>
                    ) : null}
                </div>
                <Button variant="link" size="sm" asChild>
                    <Link href={`/sessions/${sessionId}/transcript`}>{t('transcripts.open_full')}</Link>
                </Button>
            </PanelHead>

            <PanelBody className="px-4 py-3">
                {error ? (
                    <Notice tone="danger" compact className="mb-3">
                        {error}
                    </Notice>
                ) : null}
                {segments.length === 0 ? (
                    <p className="text-sm text-ink-subtle">{t('transcripts.empty_live')}</p>
                ) : (
                    <AiContent showBadge={false}>
                        {inbox ? (
                            <div className="space-y-4">
                                {unassigned.length === 0 ? (
                                    <p className="text-sm text-ink-subtle">{t('transcripts.inbox_caught_up')}</p>
                                ) : (
                                    <ol className="space-y-3">
                                        {unassigned.map((segment) => (
                                            <InboxTurn
                                                key={`${segment.index}-${segment.start}`}
                                                segment={segment}
                                                speakerItems={speakerItems}
                                                choice={choice[segment.index] ?? ''}
                                                pending={pendingIndex === segment.index}
                                                threshold={threshold}
                                                t={t}
                                                onChoice={(value) =>
                                                    setChoice((current) => ({ ...current, [segment.index]: value }))
                                                }
                                                onAssign={() => assign(segment, choice[segment.index] ?? '')}
                                            />
                                        ))}
                                    </ol>
                                )}
                                {recent.length > 0 ? (
                                    <ol className="space-y-3 border-t border-line pt-3">
                                        {recent.map((segment) => (
                                            <WatchTurn
                                                key={`${segment.index}-${segment.start}`}
                                                segment={segment}
                                                threshold={threshold}
                                                t={t}
                                            />
                                        ))}
                                    </ol>
                                ) : null}
                            </div>
                        ) : (
                            <ol className="space-y-3">
                                {[...unassigned, ...recent].map((segment) => (
                                    <WatchTurn
                                        key={`${segment.index}-${segment.start}`}
                                        segment={segment}
                                        threshold={threshold}
                                        t={t}
                                    />
                                ))}
                            </ol>
                        )}
                    </AiContent>
                )}
            </PanelBody>
        </Panel>
    );
}

function WatchTurn({
    segment,
    threshold,
    t,
}: {
    segment: TranscriptSegment;
    threshold: number;
    t: Translate;
}) {
    const unverified = isLowConfidence(segment.confidence, threshold);
    const speaker = segmentSpeakerLabel(segment, t('transcripts.unattributed'));

    return (
        <li className="relative pl-4">
            <span aria-hidden="true" className="absolute top-1.5 left-0 size-1.5 rounded-full bg-line-strong" />
            <p className="flex flex-wrap items-baseline gap-x-2">
                <span className="text-xs font-semibold text-ink">{speaker}</span>
                <span className="font-mono text-2xs text-ink-faint">{formatSegmentTime(segment.start)}</span>
                {unverified ? (
                    <span className="text-2xs font-medium text-warning">{t('transcripts.low_confidence')}</span>
                ) : null}
            </p>
            <p className={cn('mt-0.5 text-xs leading-5', unverified ? 'text-ink-subtle' : 'text-ink-muted')}>
                {segment.text}
            </p>
        </li>
    );
}

function InboxTurn({
    segment,
    speakerItems,
    choice,
    pending,
    threshold,
    t,
    onChoice,
    onAssign,
}: {
    segment: TranscriptSegment;
    speakerItems: { value: string; label: string }[];
    choice: string;
    pending: boolean;
    threshold: number;
    t: Translate;
    onChoice: (value: string) => void;
    onAssign: () => void;
}) {
    const unverified = isLowConfidence(segment.confidence, threshold);

    return (
        <li className="space-y-2 rounded-[var(--radius-md)] border border-line bg-canvas-sunk/40 p-3">
            <p className="flex flex-wrap items-baseline gap-x-2">
                <span className="text-xs font-semibold text-ink">{t('transcripts.unattributed')}</span>
                <span className="font-mono text-2xs text-ink-faint">{formatSegmentTime(segment.start)}</span>
                {unverified ? (
                    <span className="text-2xs font-medium text-warning">{t('transcripts.low_confidence')}</span>
                ) : null}
            </p>
            <p className={cn('text-xs leading-5', unverified ? 'text-ink-subtle' : 'text-ink-muted')}>{segment.text}</p>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <SimpleSelect
                    value={choice}
                    onValueChange={onChoice}
                    noneLabel={t('transcripts.pick_speaker')}
                    items={[
                        { value: '__gallery__', label: t('transcripts.gallery') },
                        ...speakerItems,
                    ]}
                    disabled={pending}
                    className="h-8 text-xs"
                />
                <Button type="button" size="sm" disabled={pending || !choice} onClick={onAssign}>
                    {pending ? t('transcripts.assigning') : t('transcripts.assign_speaker')}
                </Button>
            </div>
        </li>
    );
}
