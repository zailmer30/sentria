import type { CommitteeReportDetail } from '@/components/documents/CommitteeReportBody';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Textarea } from '@/components/ui/input';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { StatusChip, type StatusTone } from '@/components/ui/status';
import type { MinutesCorrectionRow } from '@/components/session/MinutesCorrectionsPanel';
import type { QuorumSummary } from '@/components/session/QuorumCard';
import { useOfflineVoteQueue } from '@/hooks/useOfflineVoteQueue';
import { withHonorific } from '@/lib/sessionFloor';
import { cn } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { Ban, Check, Coffee, Info, Minus, Pause, Play, SkipBack, SkipForward, X } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';

export type Translate = (key: string, replacements?: Record<string, string | number>) => string;

/** Zero-pad the leading segment so the chamber reads "04" and "08.1", not "4" or "81". */
export function formatAgendaNumber(value: string | null | undefined, fallbackIndex?: number): string {
    if (value) {
        const parts = value.split('.');

        if (parts.length > 0 && parts.every((part) => /^\d+$/.test(part))) {
            const head = parts[0] ?? '';

            return [head.padStart(2, '0'), ...parts.slice(1)].join('.');
        }

        const digits = value.replace(/\D/g, '');
        if (digits.length > 0) {
            return digits.padStart(2, '0');
        }

        return value;
    }

    if (fallbackIndex !== undefined) {
        return String(fallbackIndex + 1).padStart(2, '0');
    }

    return '';
}

export type AgendaItem = {
    id: string;
    item_number: string | null;
    title: string;
    description: string | null;
    category?: string | null;
    status: string;
    voting_open?: boolean;
    voting_round?: number;
    can_second_reading?: boolean;
    can_third_reading?: boolean;
    placed_on_third_reading?: boolean;
    can_postpone?: boolean;
    can_undo?: boolean;
    committee_hour_action?: 'second-reading' | 'archive' | null;
    can_record_committee_hour_motion?: boolean;
    committee_hour_recommendation?: string | null;
    committee_report?: CommitteeReportDetail | null;
};

export type ReadingPackDocument = {
    id: string;
    slug: string;
    title: string;
    reference_number?: string | null;
    document_type?: string | null;
    document_type_label?: string | null;
    status?: string | null;
    status_label?: string | null;
    author?: string | null;
    abstract?: string | null;
    committee_id?: string | null;
    committee?: string | null;
    open_referral?: {
        id: string;
        committee_id: string;
        committee: string | null;
        committee_ids?: string[];
        committees?: string[];
        status: string;
        meeting_on?: string | null;
        remarks?: string | null;
    } | null;
    version_id: string | null;
    mime_type: string | null;
    can_preview: boolean;
    preview_url: string | null;
    annotations_url: string | null;
};

export type CalendarDocketItem = {
    id: string;
    agenda_item_id?: string | null;
    document_id?: string | null;
    item_number?: string | null;
    title: string;
    reference_number?: string | null;
    status: string;
    can_second_reading?: boolean;
    can_third_reading?: boolean;
    placed_on_third_reading?: boolean;
    can_postpone?: boolean;
    can_undo?: boolean;
    can_refer?: boolean;
    can_edit_referral?: boolean;
    referral_agenda_item_id?: string | null;
    carried_to?: { id: string; session_number: string; title: string } | null;
    document?: {
        title: string;
        slug?: string;
        status?: string;
        committee_id?: string | null;
        committee?: string | null;
        open_referral?: {
            committee_id: string;
            committee?: string | null;
            committee_ids?: string[];
            committees?: string[];
            meeting_on?: string | null;
            remarks?: string | null;
        } | null;
    } | null;
};

export type ReadingPackItem = {
    id: string;
    parent_id?: string | null;
    position: number;
    item_number: string | null;
    title: string;
    description: string | null;
    category?: string | null;
    status: string;
    requires_vote: boolean;
    voting_open: boolean;
    reading_number?: number | null;
    title_only?: boolean;
    can_refer?: boolean;
    can_edit_referral?: boolean;
    can_second_reading?: boolean;
    can_third_reading?: boolean;
    placed_on_third_reading?: boolean;
    can_postpone?: boolean;
    can_undo?: boolean;
    carried_to?: { id: string; session_number: string; title: string } | null;
    committee_hour_action?: 'second-reading' | 'archive' | null;
    can_record_committee_hour_motion?: boolean;
    committee_hour_recommendation?: string | null;
    committee_report?: CommitteeReportDetail | null;
    document: ReadingPackDocument | null;
};

export function referredCommitteeNames(
    document?: {
        committee?: string | null;
        open_referral?: { committee?: string | null; committees?: string[] } | null;
    } | null,
): string[] {
    const fromOpen = (document?.open_referral?.committees ?? []).filter(
        (name): name is string => typeof name === 'string' && name.trim() !== '',
    );

    if (fromOpen.length > 0) {
        return fromOpen;
    }

    const fallback = document?.open_referral?.committee ?? document?.committee ?? null;

    return fallback && fallback.trim() !== '' ? [fallback] : [];
}

export type Motion = {
    id: string;
    text: string;
    type: string;
    status: string;
    mover: string | null;
    seconder: string | null;
    moved_at: string | null;
    can?: {
        second?: boolean;
        withdraw?: boolean;
        rule?: boolean;
    };
};

export type VotingMember = {
    id: string;
    display_name: string | null;
    avatar_url: string | null;
    position_title?: string | null;
    district?: string | null;
    status: string;
    has_voted: boolean;
    choice?: string | null;
    cast_at?: string | null;
    is_presiding?: boolean;
};

export type VotingState = {
    open: boolean;
    round: number;
    silent?: boolean;
    tallies: { yes: number; no: number; abstain: number; inhibit: number; total: number };
    user_vote: string | null;
    electronic_is_binding: boolean;
    awaiting_count?: number;
    members?: VotingMember[];
    previous?: {
        round: number;
        silent?: boolean;
        tallies: { yes: number; no: number; abstain: number; inhibit: number; total: number };
        members: VotingMember[];
    } | null;
};

export type PrivateNoteRow = {
    id: string;
    body: string;
    page_number?: number | null;
    notable_type?: string;
    notable_id?: string;
    updated_at?: string | null;
};

export type RecognitionRequest = {
    id: string;
    user_id: string;
    display_name: string | null;
    avatar_url?: string | null;
    agenda_item_id: string | null;
    item_number: string | null;
    item_title: string | null;
    status: string;
    raised_at: string | null;
    can?: {
        cancel?: boolean;
        recognize?: boolean;
        dismiss?: boolean;
    };
};

export type RecognitionState = {
    pending: RecognitionRequest[];
    recognized: RecognitionRequest | null;
};

export type FloorProps = {
    session: {
        id: string;
        title: string;
        type?: string | null;
        type_label?: string | null;
        status: string;
        status_label: string;
        venue?: string | null;
        presiding_officer?: string | null;
        presiding_officer_id?: string | null;
        recording_enabled?: boolean;
        defer_heading_votes?: boolean;
        capture_mode?: 'mixer_mix' | 'per_seat';
        secretariat_minutes?: string | null;
        recess_ends_at?: string | null;
        recess_remaining_seconds?: number | null;
    };
    current_item: AgendaItem | null;
    next_item: AgendaItem | null;
    previous_item?: AgendaItem | null;
    quorum: QuorumSummary;
    document_link: { slug: string; title: string } | null;
    reading_pack?: ReadingPackItem[];
    minutes_corrections?: MinutesCorrectionRow[];
    calendar_docket?: CalendarDocketItem[];
    hall_display?: {
        stage: 'item' | 'document' | 'report' | 'results';
        agenda_item_id: string | null;
        view?: {
            zoom: number;
            page: number;
            relative_x: number;
            relative_y: number;
        } | null;
    };
    private_notes: PrivateNoteRow[];
    attendance: {
        id: string;
        user_id?: string | null;
        display_name: string | null;
        avatar_url?: string | null;
        position_title?: string | null;
        district?: string | null;
        status: string;
        remarks?: string | null;
    }[];
    elapsed_seconds: number | null;
    voting: VotingState;
    motions: Motion[];
    recognition?: RecognitionState;
    can: Record<string, boolean>;
    advance_blocked_reason?: string | null;
    committees?: { id: string; name: string }[];
    assistant?: import('@/components/session/SessionAssistantPanel').SessionAssistantData | null;
    transcript?: {
        id: string;
        status: string;
        processing_error?: string | null;
        segments: import('@/lib/echo').TranscriptSegment[];
    } | null;
    workspace?: 'console' | 'minutes' | 'recording';
};

/**
 * The running clock on the console plate. Reads as a clock (`01:12`) rather
 * than as prose, because it sits beside two other counted facts and they have
 * to scan as one row of figures.
 */
export function formatClockElapsed(seconds: number | null): string {
    if (seconds === null || seconds < 0) {
        return '—';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

/**
 * Tick a recess from the server's remaining-seconds snapshot, not from the
 * browser's wall clock against recess_ends_at. Otherwise a laptop a few
 * seconds behind the clerk desk opens on 10:31 for a ten-minute recess.
 */
export function useRecessRemaining(remainingFromServer: number | null | undefined): number | null {
    const now = useNow();
    const [endMs, setEndMs] = useState<number | null>(null);

    useEffect(() => {
        if (remainingFromServer == null) {
            setEndMs(null);

            return;
        }

        setEndMs(Date.now() + remainingFromServer * 1000);
    }, [remainingFromServer]);

    if (endMs === null || now === null) {
        return null;
    }

    return Math.max(0, Math.floor((endMs - now) / 1000));
}

export function formatClockCountdown(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

/** Null until mounted so the server pass cannot disagree with the chamber clock. */
export function useNow(): number | null {
    const [now, setNow] = useState<number | null>(null);

    useEffect(() => {
        function read() {
            setNow(Date.now());
        }

        read();
        const timer = window.setInterval(read, 1000);

        return () => window.clearInterval(timer);
    }, []);

    return now;
}

export function isTimedRecess(session: { status: string; recess_ends_at?: string | null }): boolean {
    return session.status === 'suspended' && Boolean(session.recess_ends_at);
}

/** Wall-clock time of a recorded event, for the motion ledger's left column. */
export function formatTimeOfDay(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
}

export function VotingBindingBanner({ voting, t }: { voting: VotingState; t: (key: string) => string }) {
    if (voting.electronic_is_binding) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex items-start gap-2 rounded-[var(--radius-lg)] border border-[var(--color-warning-line)] bg-[var(--color-warning-soft)] px-3 py-2.5 text-sm text-warning"
        >
            <Info aria-hidden="true" className="mt-px size-4 shrink-0" strokeWidth={2} />
            <p className="flex-1 leading-5">{t('sessions.voting_not_binding')}</p>
        </div>
    );
}

export type FloorControl = {
    key: string;
    route: string;
    label: string;
    icon: typeof Play;
    enabled: boolean;
};

/** First-reading measures still awaiting committee referral. */
const UNREFERRED_FIRST_READING_STATUSES = ['agenda-inclusion', 'reading-deliberation'];

export function isUnreferredFirstReading(item: ReadingPackItem | null | undefined): boolean {
    if (!item?.document || item.reading_number !== 1) {
        return false;
    }

    return UNREFERRED_FIRST_READING_STATUSES.includes(item.document.status ?? '');
}

export type FloorControlModel = {
    floor: FloorControl[];
    advance: FloorControl;
    retreat: FloorControl;
    adjournEnabled: boolean;
    showOpenVoting: boolean;
    showCloseVoting: boolean;
};

/**
 * Which floor actions are available right now. Extracted so the clerk's desk
 * cannot drift from the rules when the same controls are reused. Advancing is
 * refused while a ballot is still open. Retreat restores the previous item
 * after an accidental advance.
 */
export function floorControlModel({
    sessionStatus,
    currentItemId,
    previousItemId = null,
    nextItem = null,
    can,
    voting,
    t,
    advanceBlockedReason = null,
}: {
    sessionStatus: string;
    currentItemId: string | null;
    previousItemId?: string | null;
    nextItem?: { category?: string | null } | null;
    can: Record<string, boolean>;
    voting: VotingState;
    t: (key: string) => string;
    advanceBlockedReason?: string | null;
}): FloorControlModel {
    const agendaLive =
        Boolean(can.manage_agenda) && (sessionStatus === 'in-session' || sessionStatus === 'suspended') && !voting.open;
    const beginHeadingVotes = Boolean(can.begin_heading_votes) && agendaLive;
    const openingCallToOrder =
        !beginHeadingVotes && currentItemId === null && nextItem?.category === 'call-to-order';

    return {
        floor: [
            {
                key: 'start',
                route: 'start',
                label: t('sessions.action_start'),
                icon: Play,
                enabled: Boolean(can.start) && (sessionStatus === 'scheduled' || sessionStatus === 'documents-distributed'),
            },
            {
                key: 'recess',
                route: 'recess',
                label: t('sessions.action_recess'),
                icon: Coffee,
                enabled: Boolean(can.suspend) && sessionStatus === 'in-session' && !voting.open,
            },
            {
                key: 'suspend',
                route: 'suspend',
                label: t('sessions.action_suspend'),
                icon: Pause,
                enabled: Boolean(can.suspend) && sessionStatus === 'in-session',
            },
            {
                key: 'resume',
                route: 'resume',
                label: t('sessions.action_resume'),
                icon: Play,
                enabled: Boolean(can.resume) && sessionStatus === 'suspended',
            },
        ],
        retreat: {
            key: 'retreat',
            route: 'agenda/retreat',
            label: t('sessions.action_previous_item'),
            icon: SkipBack,
            enabled: agendaLive && Boolean(previousItemId),
        },
        advance: {
            key: beginHeadingVotes ? 'begin-heading-votes' : 'advance',
            route: beginHeadingVotes ? 'agenda/begin-heading-votes' : 'agenda/advance',
            label: beginHeadingVotes
                ? t('sessions.action_begin_heading_votes')
                : openingCallToOrder
                  ? t('sessions.action_call_to_order')
                  : t('sessions.action_next_item'),
            icon: SkipForward,
            enabled: agendaLive && (beginHeadingVotes || !advanceBlockedReason),
        },
        adjournEnabled: Boolean(can.adjourn) && (sessionStatus === 'in-session' || sessionStatus === 'suspended'),
        showOpenVoting: Boolean(can.open_voting && currentItemId && !voting.open),
        showCloseVoting: Boolean(can.close_voting && currentItemId && voting.open),
    };
}

export function MotionRecorder({
    sessionId,
    currentItemId,
    canCreate,
    t,
    embedded = false,
    variant = 'record',
    itemLabel = null,
    recognized = null,
}: {
    sessionId: string;
    currentItemId: string | null;
    canCreate: boolean;
    t: Translate;
    /** Omit the panel chrome when this form sits inside a sheet or another panel. */
    embedded?: boolean;
    variant?: 'record' | 'raise';
    itemLabel?: string | null;
    recognized?: RecognitionRequest | null;
}) {
    const spokenItemId = recognized?.agenda_item_id ?? currentItemId;
    const recordingSpoken = recognized !== null;
    const allowed = canCreate || recordingSpoken;
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);

    const motionForm = useForm({
        agenda_item_id: spokenItemId ?? '',
        text: '',
        type: 'main',
        moved_by: recognized?.user_id ?? '',
    });

    useEffect(() => {
        if (!recordingSpoken) {
            return;
        }

        motionForm.setData((data) => ({
            ...data,
            agenda_item_id: spokenItemId ?? '',
            moved_by: recognized?.user_id ?? '',
        }));
        panelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        textareaRef.current?.focus();
        // Sync attribution when Echo reloads recognition without remounting the form.
        // eslint-disable-next-line react-hooks/exhaustive-deps -- motionForm identity is unstable
    }, [recordingSpoken, recognized?.id, spokenItemId]);

    if (!allowed) {
        return null;
    }

    function submitMotion(event: FormEvent) {
        event.preventDefault();
        if (!spokenItemId) {
            return;
        }

        motionForm.transform((data) => ({
            ...data,
            agenda_item_id: spokenItemId,
            type: 'main',
            moved_by: recognized?.user_id || undefined,
        }));

        motionForm.post(`/sessions/${sessionId}/motions`, {
            preserveScroll: true,
            onSuccess: () => motionForm.setData('text', ''),
        });
    }

    const raising = variant === 'raise';
    const spokenLabel = recognized
        ? [recognized.item_number, recognized.item_title].filter(Boolean).join(' ')
        : itemLabel;
    const hint = recordingSpoken
        ? t('sessions.record_spoken_motion_hint', {
              name: withHonorific(recognized?.display_name) ?? recognized?.display_name ?? '',
              item: spokenLabel || t('sessions.current_item'),
          })
        : spokenItemId
          ? raising
              ? spokenLabel
                  ? t('sessions.raise_motion_on', { item: spokenLabel })
                  : t('sessions.raise_motion_hint')
              : undefined
          : t('sessions.motion_needs_item');
    const ready = Boolean(spokenItemId) && motionForm.data.text.trim().length >= 3 && !motionForm.processing;

    const form = (
        <form onSubmit={submitMotion} className="space-y-3">
            <Field
                id="motion-text"
                label={
                    recordingSpoken
                        ? t('sessions.record_spoken_motion')
                        : raising
                          ? t('sessions.raise_motion')
                          : t('sessions.record_motion')
                }
                hint={hint}
            >
                <Textarea
                    {...fieldAria('motion-text', { hint })}
                    ref={textareaRef}
                    value={motionForm.data.text}
                    onChange={(e) => motionForm.setData('text', e.target.value)}
                    rows={3}
                    placeholder={t('sessions.motion_placeholder')}
                    disabled={!spokenItemId}
                />
            </Field>
            <Button type="submit" variant="primary" size="floor" disabled={!ready}>
                {raising && !recordingSpoken ? t('sessions.action_raise_motion') : t('sessions.submit_motion')}
            </Button>
        </form>
    );

    if (embedded) {
        return form;
    }

    return (
        <div ref={panelRef}>
            <Panel as="section" live={recordingSpoken} raised={recordingSpoken}>
                <PanelBody>{form}</PanelBody>
            </Panel>
        </div>
    );
}

const BALLOT_CHOICES = [
    {
        value: 'yes',
        labelKey: 'sessions.vote_yes',
        hintKey: 'sessions.vote_yes_hint',
        icon: Check,
        iconWrap: 'bg-success-soft text-success',
        selected: 'border-success bg-success-soft ring-1 ring-success',
    },
    {
        value: 'no',
        labelKey: 'sessions.vote_no',
        hintKey: 'sessions.vote_no_hint',
        icon: X,
        iconWrap: 'bg-critical-soft text-critical',
        selected: 'border-critical bg-critical-soft ring-1 ring-critical',
    },
    {
        value: 'abstain',
        labelKey: 'sessions.vote_abstain',
        hintKey: 'sessions.vote_abstain_hint',
        icon: Minus,
        iconWrap: 'bg-warning-soft text-warning',
        selected: 'border-[var(--color-warning)] bg-warning-soft ring-1 ring-[var(--color-warning)]',
    },
    {
        value: 'inhibit',
        labelKey: 'sessions.vote_inhibit',
        hintKey: 'sessions.vote_inhibit_hint',
        icon: Ban,
        iconWrap: 'bg-[var(--color-vote-inhibit-soft)] text-[var(--color-vote-inhibit)]',
        selected:
            'border-[var(--color-vote-inhibit)] bg-[var(--color-vote-inhibit-soft)] ring-1 ring-[var(--color-vote-inhibit)]',
    },
] as const;

export function MemberVotePanel({
    sessionId,
    currentItem,
    voting,
    can,
    t,
    variant = 'panel',
    expectedBallots,
}: {
    sessionId: string;
    currentItem: AgendaItem | null;
    voting: VotingState;
    can: Record<string, boolean>;
    t: (key: string, replacements?: Record<string, string | number>) => string;
    /** `ballot` is the member-floor card; `bar` is the compact tablet strip. */
    variant?: 'panel' | 'bar' | 'ballot';
    expectedBallots?: number;
}) {
    const { online, queueVote, getQueuedChoice, pending, flushing } = useOfflineVoteQueue();

    if (!can.cast_vote || !currentItem || !voting.open) {
        return null;
    }

    const queuedChoice = getQueuedChoice(sessionId, currentItem.id, voting.round);
    const recordedChoice = queuedChoice ?? voting.user_vote;

    function cast(choice: string) {
        if (!currentItem || recordedChoice === choice) {
            return;
        }

        if (!online) {
            queueVote({
                sessionId,
                agendaItemId: currentItem.id,
                votingRound: voting.round,
                choice,
            });

            return;
        }

        router.post(
            `/sessions/${sessionId}/voting/cast`,
            {
                agenda_item_id: currentItem.id,
                choice,
                voting_round: voting.round,
            },
            { preserveScroll: true },
        );
    }

    const statusHints = (
        <>
            <p className="text-xs text-ink-muted">{t('sessions.vote_change_hint')}</p>
            {!online ? <p className="mt-1 text-sm text-ink-muted">{t('sessions.vote_offline_hint')}</p> : null}
            {flushing || pending.length > 0 ? (
                <p className="mt-2 text-sm text-ink-muted">{t('sessions.syncing_votes')}</p>
            ) : null}
        </>
    );

    if (variant === 'ballot') {
        const itemLabel = currentItem.item_number ?? currentItem.title;
        const expected = expectedBallots && expectedBallots > 0 ? expectedBallots : undefined;

        return (
            <Panel as="section" live raised>
                <PanelBody className="space-y-2.5 py-3">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                            <h2 className="text-sm font-semibold tracking-[-0.015em] text-ink">
                                {t('sessions.cast_vote')}
                            </h2>
                            <p className="mt-0.5 text-xs text-ink-muted">
                                {expected
                                    ? t('sessions.ballot_open_on', {
                                          item: itemLabel,
                                          count: voting.tallies.total,
                                          of: expected,
                                      })
                                    : t('sessions.ballot_open_on_count', {
                                          item: itemLabel,
                                          count: voting.tallies.total,
                                      })}
                            </p>
                        </div>
                        <StatusChip tone={recordedChoice ? 'final' : 'review'} size="sm">
                            {recordedChoice
                                ? queuedChoice
                                    ? t('sessions.vote_queued')
                                    : t('sessions.vote_recorded')
                                : t('sessions.not_yet_voted')}
                        </StatusChip>
                    </div>

                    {statusHints}

                    <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                        {BALLOT_CHOICES.map((choice) => {
                            const Icon = choice.icon;
                            const selected = recordedChoice === choice.value;

                            return (
                                <button
                                    key={choice.value}
                                    type="button"
                                    aria-pressed={selected}
                                    onClick={() => cast(choice.value)}
                                    className={cn(
                                        'flex min-h-16 flex-col items-center justify-center gap-1 rounded-[var(--radius-md)] border px-2 py-2 text-center transition-colors',
                                        selected
                                            ? choice.selected
                                            : 'border-line bg-surface hover:border-[var(--color-accent-line)] hover:bg-accent-soft/40',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-7 items-center justify-center rounded-full',
                                            choice.iconWrap,
                                        )}
                                    >
                                        <Icon aria-hidden className="size-3.5" strokeWidth={2.25} />
                                    </span>
                                    <span className="text-xs font-semibold text-ink">{t(choice.labelKey)}</span>
                                    <span className="text-2xs text-ink-muted">{t(choice.hintKey)}</span>
                                </button>
                            );
                        })}
                    </div>
                </PanelBody>
                {!voting.electronic_is_binding ? (
                    <div
                        role="status"
                        className="border-t border-[var(--color-warning-line)] bg-warning-soft px-4 py-2 text-2xs leading-4 text-warning"
                    >
                        {t('sessions.voting_not_binding')}
                    </div>
                ) : null}
            </Panel>
        );
    }

    const voteButtons = (
        <div className={variant === 'bar' ? 'grid grid-cols-4 gap-2' : 'mt-4 grid grid-cols-2 gap-3 md:grid-cols-4'}>
            {BALLOT_CHOICES.map((choice) => (
                <Button
                    key={choice.value}
                    size="floor"
                    variant={recordedChoice === choice.value ? 'primary' : 'secondary'}
                    onClick={() => cast(choice.value)}
                >
                    {t(choice.labelKey)}
                </Button>
            ))}
        </div>
    );

    if (variant === 'bar') {
        return (
            <div className="border-t border-live-line bg-surface px-3 py-3 shadow-[var(--shadow-md)]">
                <div className="mb-2 flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold text-ink">{t('sessions.cast_vote')}</p>
                    {currentItem.item_number ? (
                        <span className="font-mono text-xs text-ink-muted">{currentItem.item_number}</span>
                    ) : null}
                </div>
                {statusHints}
                {voteButtons}
            </div>
        );
    }

    return (
        <Panel as="section">
            <PanelHead>
                <PanelTitle>{t('sessions.cast_vote')}</PanelTitle>
            </PanelHead>
            <PanelBody>
                {statusHints}
                {voteButtons}
            </PanelBody>
        </Panel>
    );
}

export function motionTone(status: string): StatusTone {
    if (status === 'voting_open') {
        return 'live';
    }

    if (status === 'carried') {
        return 'final';
    }

    if (status === 'lost' || status === 'withdrawn' || status === 'ruled_out') {
        return 'closed';
    }

    if (status === 'referred') {
        return 'review';
    }

    return 'moving';
}

export function motionStatusLabel(status: string, t: (key: string) => string): string {
    const key = `sessions.motion_status.${status}`;
    const label = t(key);

    return label === key ? status : label;
}

/**
 * The chair's disposition of a motion. Only ever opened from a surface the
 * presiding officer operates — the record has to name who ruled.
 */
export function RuleMotionDialog({
    sessionId,
    motionId,
    open,
    onOpenChange,
    t,
}: {
    sessionId: string;
    motionId: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    t: (key: string) => string;
}) {
    const form = useForm({
        disposition: 'carried',
        notes: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/sessions/${sessionId}/motions/${motionId}/rule`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('sessions.motion_rule_title')} description={t('sessions.motion_rule_hint')}>
                <form onSubmit={submit} className="space-y-4">
                    <Field id="disposition" label={t('sessions.motion_disposition')} required>
                        <Select value={form.data.disposition} onValueChange={(value) => form.setData('disposition', value)}>
                            <SelectTrigger id="disposition">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="carried">{t('sessions.motion_disposition_carried')}</SelectItem>
                                <SelectItem value="lost">{t('sessions.motion_disposition_lost')}</SelectItem>
                                <SelectItem value="ruled_out">{t('sessions.motion_disposition_ruled_out')}</SelectItem>
                                <SelectItem value="referred">{t('sessions.motion_disposition_referred')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field id="notes" label={t('sessions.motion_rule_notes')} hint={t('sessions.motion_rule_notes_hint')}>
                        <Textarea
                            {...fieldAria('notes', { hint: t('sessions.motion_rule_notes_hint') })}
                            value={form.data.notes}
                            onChange={(event) => form.setData('notes', event.target.value)}
                            rows={3}
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('sessions.cancel')}
                        </Button>
                        <Button type="submit" variant="primary" disabled={form.processing}>
                            {form.processing ? t('sessions.saving') : t('sessions.motion_rule_save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}


