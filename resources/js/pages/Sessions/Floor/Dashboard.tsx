import { ChamberDocument } from '@/components/session/ChamberDocument';
import { FloorRecognitionDock } from '@/components/session/FloorRecognitionDock';
import { OrderOfBusiness } from '@/components/session/OrderOfBusiness';
import { VoteBoard, VoteTotal } from '@/components/session/VoteBoard';
import { Panel, PanelBody } from '@/components/ui/panel';
import type { HallDocumentView } from '@/components/documents/DocumentPdfViewer';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';
import { useSessionEcho } from '@/hooks/useSessionEcho';
import SessionLayout from '@/layouts/SessionLayout';
import { useTranslations } from '@/lib/i18n';
import { sessionDisplayTitle, withHonorific } from '@/lib/sessionFloor';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { FileText, Maximize2, Minimize2, WifiOff } from 'lucide-react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import {
    formatAgendaNumber,
    formatClockCountdown,
    isTimedRecess,
    useNow,
    useRecessRemaining,
    VotingBindingBanner,
    type AgendaItem,
    type FloorProps,
    type RecognitionState,
    type Translate,
} from './shared';

/**
 * The hall board. Not a dashboard anyone operates — a surface the whole chamber
 * reads at once, from the benches, the gallery and the press table.
 *
 * Everything here follows from that. Type is sized in viewport units so the
 * same markup carries from a laptop preview to a projector wall. The board
 * shows one thing at a time, because a room cannot be asked to choose where to
 * look. And the one thing it shows is decided by the secretariat console: a
 * vote outranks a projected document, a projected document outranks a title
 * card. The board itself never decides.
 *
 * Present mode is still available as a projector gesture (fullscreen), but
 * stage pins and voting controls stay on the console.
 */

/**
 * Stable reference: `useSessionEcho` keys its subscription effect on this
 * array, so an inline literal would resubscribe on every render.
 */
const HALL_ECHO_PROPS = [
    'session',
    'current_item',
    'next_item',
    'attendance',
    'voting',
    'reading_pack',
    'hall_display',
    'recognition',
];

/** How long the room sits still before the projector controls withdraw. */
const IDLE_MS = 4000;

type HallStage = 'item' | 'document' | 'results';

type Stage = 'vote' | 'document' | 'item' | 'recess';

function parseHallStage(value: unknown): HallStage {
    if (value === 'document' || value === 'results') {
        return value;
    }

    return 'item';
}

function parseHallView(value: unknown): HallDocumentView | null {
    if (typeof value !== 'object' || value === null) {
        return null;
    }

    const record = value as Record<string, unknown>;

    if (
        typeof record.zoom !== 'number' ||
        typeof record.page !== 'number' ||
        typeof record.relative_x !== 'number' ||
        typeof record.relative_y !== 'number'
    ) {
        return null;
    }

    return {
        zoom: record.zoom,
        page: record.page,
        relative_x: record.relative_x,
        relative_y: record.relative_y,
    };
}

function parseRecognition(payload: Record<string, unknown>): RecognitionState {
    const pending = Array.isArray(payload.pending) ? (payload.pending as RecognitionState['pending']) : [];
    const recognized =
        payload.recognized && typeof payload.recognized === 'object'
            ? (payload.recognized as RecognitionState['recognized'])
            : null;

    return { pending, recognized };
}

export default function DashboardFloor({
    session,
    current_item,
    next_item,
    attendance,
    voting,
    reading_pack = [],
    hall_display = { stage: 'item', agenda_item_id: null, view: null },
    recognition = { pending: [], recognized: null },
}: FloorProps) {
    const { t } = useTranslations();
    const { organization } = usePage<PageProps>().props;
    const online = useOnlineStatus();

    const [liveHall, setLiveHall] = useState(hall_display);
    const [liveVoting, setLiveVoting] = useState(voting);
    const [liveRecognition, setLiveRecognition] = useState(recognition);
    const [documentView, setDocumentView] = useState<HallDocumentView | null>(
        () => hall_display.view ?? null,
    );

    useEffect(() => {
        setLiveHall(hall_display);
    }, [hall_display]);

    useEffect(() => {
        setLiveVoting(voting);
    }, [voting]);

    useEffect(() => {
        setLiveRecognition(recognition);
    }, [recognition]);

    useEffect(() => {
        setDocumentView(liveHall.view ?? null);
    }, [liveHall.view, liveHall.stage, liveHall.agenda_item_id]);

    const onHallDisplay = useCallback((payload: Record<string, unknown>) => {
        const stage = parseHallStage(payload.stage);
        const agendaItemId = typeof payload.agenda_item_id === 'string' ? payload.agenda_item_id : null;
        const view = parseHallView(payload.view);

        setLiveHall({
            stage,
            agenda_item_id: stage === 'item' ? null : agendaItemId,
            view: stage === 'document' ? view : null,
        });
        setDocumentView(stage === 'document' ? view : null);
    }, []);

    const onHallDisplayView = useCallback((payload: Record<string, unknown>) => {
        const view = parseHallView(payload.view);
        setDocumentView(view);
        setLiveHall((current) => ({ ...current, view }));
    }, []);

    const onVotingOpened = useCallback((payload: Record<string, unknown>) => {
        setLiveVoting((current) => ({ ...current, open: true, silent: Boolean(payload.silent) }));
    }, []);

    const onVotingClosed = useCallback(() => {
        setLiveVoting((current) => ({ ...current, open: false }));
    }, []);

    const onVoteCast = useCallback((payload: Record<string, unknown>) => {
        const tallies = payload.tallies;

        if (
            typeof tallies !== 'object' ||
            tallies === null ||
            typeof (tallies as { yes?: unknown }).yes !== 'number'
        ) {
            return;
        }

        const next = tallies as FloorProps['voting']['tallies'];

        setLiveVoting((current) => ({
            ...current,
            open: true,
            tallies: next,
        }));
    }, []);

    const onRecognition = useCallback((payload: Record<string, unknown>) => {
        setLiveRecognition(parseRecognition(payload));
    }, []);

    useSessionEcho(session.id, HALL_ECHO_PROPS, {
        onHallDisplay,
        onHallDisplayView,
        onVotingOpened,
        onVotingClosed,
        onVoteCast,
        onRecognition,
    });

    const { presenting, enter, leave, idle } = usePresentation();
    const now = useNow();
    const wallClock = formatWallClock(now);

    const present = attendance.filter((row) => row.status === 'present' || row.status === 'late');

    const projectedId =
        liveHall.stage === 'document' ? (liveHall.agenda_item_id ?? current_item?.id ?? null) : null;
    const packItem =
        (projectedId ? reading_pack.find((row) => row.id === projectedId) : null) ??
        reading_pack.find((row) => row.id === current_item?.id) ??
        null;

    const previous = liveVoting.previous ?? voting.previous ?? null;
    const showingResult = !liveVoting.open && liveHall.stage === 'results' && previous !== null;
    const recessed = isTimedRecess(session);

    const stage: Stage = liveVoting.open || showingResult
        ? 'vote'
        : recessed
          ? 'recess'
          : liveHall.stage === 'document' && packItem?.document
            ? 'document'
            : 'item';

    const controls = (
        <div
            className={cn(
                'flex flex-wrap items-center justify-end gap-1.5 transition-opacity duration-500',
                idle && 'pointer-events-none opacity-0',
            )}
        >
            <PlateButton
                onClick={presenting ? leave : enter}
                icon={presenting ? Minimize2 : Maximize2}
                label={t(presenting ? 'sessions.hall.exit_present' : 'sessions.hall.present')}
                iconOnly
            />
        </div>
    );

    return (
        <SessionLayout
            title={t('sessions.floor.dashboard')}
            sessionTitle={session.title}
            sessionId={session.id}
            sessionStatus={session.status}
            venue={session.venue}
            presidingOfficer={session.presiding_officer}
            variant="display"
        >
            <div className={cn('flex min-h-0 flex-1 flex-col bg-canvas', idle && 'cursor-none')}>
                <HallIdentity
                    organization={organization.name}
                    title={session.title}
                    venue={session.venue}
                    presidingOfficer={session.presiding_officer}
                    wallClock={wallClock}
                    live={session.status === 'in-session'}
                    online={online}
                    t={t}
                />

                <FloorRecognitionDock sessionId={session.id} recognition={liveRecognition} hall />

                <div className="grid min-h-0 flex-1 grid-cols-1 overflow-y-auto lg:grid-cols-[minmax(0,1fr)_clamp(17rem,22vw,25rem)] lg:overflow-hidden">
                    <div className="flex min-h-0 flex-col overflow-hidden">
                        {stage === 'recess' ? (
                            <RecessStage remainingFromServer={session.recess_remaining_seconds ?? 0} controls={controls} t={t} />
                        ) : (
                            <>
                                <HallPlate
                                    item={current_item}
                                    nextItem={next_item}
                                    full={stage === 'item'}
                                    voting={liveVoting.open}
                                    result={showingResult}
                                    wallClock={null}
                                    controls={controls}
                                    t={t}
                                />

                                {stage === 'document' ? (
                                    <ChamberDocument
                                        item={packItem}
                                        view={documentView}
                                        className="min-h-[24rem] flex-1 lg:min-h-0"
                                    />
                                ) : null}

                                {stage === 'vote' ? (
                                    <VoteStage
                                        voting={showingResult && previous ? { ...liveVoting, ...previous } : liveVoting}
                                        expected={present.length}
                                        measure={packItem?.document?.title ?? null}
                                        closed={showingResult}
                                        t={t}
                                    />
                                ) : null}
                            </>
                        )}
                    </div>

                    <aside className="flex min-h-0 flex-col gap-2.5 border-t border-line bg-canvas-sunk p-2.5 lg:border-t-0 lg:border-l">
                        {liveVoting.open && stage !== 'vote' ? (
                            <Panel as="section" live className="shrink-0">
                                <PanelBody className="space-y-2.5 px-4 py-3.5">
                                    <p className="label-eyebrow">{t('sessions.voting_open_label')}</p>
                                    <VoteBoard tallies={liveVoting.tallies} />
                                    <VoteTotal tallies={liveVoting.tallies} expected={present.length || undefined} />
                                </PanelBody>
                            </Panel>
                        ) : null}

                        <OrderOfBusiness
                            display
                            items={reading_pack}
                            currentItemId={current_item?.id ?? null}
                            className="min-h-[16rem] lg:min-h-0 lg:flex-1"
                        />
                    </aside>
                </div>
            </div>
        </SessionLayout>
    );
}

/**
 * Who is sitting, and is this board still connected.
 */
function HallIdentity({
    organization,
    title,
    venue,
    presidingOfficer,
    wallClock,
    live,
    online,
    t,
}: {
    organization: string;
    title: string;
    venue?: string | null;
    presidingOfficer?: string | null;
    wallClock: string | null;
    live: boolean;
    online: boolean;
    t: Translate;
}) {
    const officer = withHonorific(presidingOfficer);
    const meta = [organization, venue, officer ? t('sessions.presided_by', { name: officer }) : null].filter(Boolean);

    return (
        <header className="flex shrink-0 flex-wrap items-center justify-between gap-x-6 gap-y-1 border-b border-line bg-surface px-5 py-2.5 md:px-7">
            <div className="min-w-0">
                <h1 className="truncate text-[clamp(0.9375rem,1.3vw,1.5rem)] font-semibold tracking-[-0.015em] text-ink">
                    {sessionDisplayTitle(title)}
                </h1>
                <p className="truncate text-[clamp(0.75rem,0.9vw,1rem)] text-ink-muted">{meta.join(' · ')}</p>
            </div>

            <div className="flex shrink-0 items-center gap-3">
                {!online ? (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-warning-soft)] px-2.5 py-1 text-[clamp(0.6875rem,0.85vw,0.9375rem)] font-semibold text-warning">
                        <WifiOff aria-hidden="true" strokeWidth={2} className="size-3.5" />
                        {t('sessions.hall.disconnected')}
                    </span>
                ) : null}
                {live ? (
                    <span className="inline-flex items-center gap-1.5 text-[clamp(0.6875rem,0.85vw,0.9375rem)] font-semibold tracking-wide text-live uppercase">
                        <span aria-hidden="true" className="animate-live-pulse size-1.5 rounded-full bg-live" />
                        {t('sessions.floor.live')}
                    </span>
                ) : null}
                <span className="font-mono text-[clamp(0.9375rem,1.3vw,1.5rem)] font-medium text-ink tabular-nums">
                    {wallClock ?? '—'}
                </span>
            </div>
        </header>
    );
}

/**
 * The item on the floor, on the chamber's navy plate. It runs the full height
 * of the stage when there is nothing else to show, and shrinks to a band when
 * the document or the tally takes over — but it never leaves, because a room
 * that loses track of which item is being debated has lost the sitting.
 */
function HallPlate({
    item,
    nextItem,
    full,
    voting,
    result = false,
    wallClock,
    controls,
    t,
}: {
    item: AgendaItem | null;
    nextItem: AgendaItem | null;
    full: boolean;
    voting: boolean;
    result?: boolean;
    wallClock: string | null;
    controls: ReactNode;
    t: Translate;
}) {
    return (
        <section
            className={cn(
                'relative flex flex-col overflow-hidden bg-floor-plate px-5 text-floor-ink md:px-7',
                full ? 'min-h-[18rem] flex-1 justify-center py-8' : 'shrink-0 py-4',
            )}
        >
            <div className="flex items-start justify-between gap-4">
                <div className="flex min-w-0 flex-wrap items-center gap-2.5">
                    <p className="text-eyebrow text-floor-ink-faint">{t('sessions.current_item')}</p>
                    {voting ? (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-live px-2.5 py-1 text-2xs font-bold tracking-wide text-live-on uppercase">
                            <span aria-hidden="true" className="animate-live-pulse size-1.5 rounded-full bg-live-on" />
                            {t('sessions.voting_open_label')}
                        </span>
                    ) : null}
                    {result ? (
                        <span className="inline-flex items-center rounded-full border border-floor-line bg-floor-sunk px-2.5 py-1 text-2xs font-bold tracking-wide text-floor-ink-muted uppercase">
                            {t('sessions.hall.vote_result')}
                        </span>
                    ) : null}
                    {wallClock !== null ? (
                        <span className="font-mono text-2xs text-floor-ink-faint tabular-nums">{wallClock}</span>
                    ) : null}
                </div>

                {controls}
            </div>

            {item ? (
                <div className={cn('min-w-0', full ? 'mt-6' : 'mt-2')}>
                    <h2
                        className={cn(
                            'flex flex-wrap items-baseline gap-x-[0.4em] font-semibold tracking-[-0.025em] text-floor-ink',
                            full
                                ? 'text-[clamp(2rem,5.5vw,6rem)] leading-[1.05]'
                                : 'text-[clamp(1.25rem,2.6vw,3rem)] leading-tight',
                        )}
                    >
                        {item.item_number ? (
                            <span className="font-mono font-bold tracking-[-0.04em] text-floor-ink-muted">
                                {formatAgendaNumber(item.item_number)}
                            </span>
                        ) : null}
                        <span className="min-w-0">{item.title}</span>
                    </h2>

                    {full && item.description ? (
                        <p className="mt-6 max-w-[60ch] text-[clamp(1rem,1.5vw,1.75rem)] leading-relaxed text-floor-ink-muted">
                            {item.description}
                        </p>
                    ) : null}
                </div>
            ) : (
                <p
                    className={cn(
                        'text-floor-ink-muted',
                        full ? 'mt-6 text-[clamp(1.5rem,3.5vw,3.5rem)]' : 'mt-2 text-[clamp(1rem,2vw,2rem)]',
                    )}
                >
                    {t('sessions.no_current_item')}
                </p>
            )}

            {full && nextItem ? (
                <p className="mt-auto flex flex-wrap items-baseline gap-x-2.5 pt-8 text-[clamp(0.875rem,1.2vw,1.375rem)] text-floor-ink-faint">
                    <span className="text-eyebrow">{t('sessions.next_item')}</span>
                    {nextItem.item_number ? <span className="font-mono">{formatAgendaNumber(nextItem.item_number)}</span> : null}
                    <span className="text-floor-ink-muted">{nextItem.title}</span>
                </p>
            ) : null}
        </section>
    );
}

/** The tally at chamber scale, with each member under the side they took. */
function VoteStage({
    voting,
    expected,
    measure,
    closed = false,
    t,
}: {
    voting: FloorProps['voting'];
    expected: number;
    /** Title of the document the vote is on, if the item carries one. */
    measure: string | null;
    closed?: boolean;
    t: Translate;
}) {
    const anonymous = Boolean(voting.silent) && !closed;

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden p-3 md:p-4">
            <Panel as="section" live={voting.open && !closed} className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <PanelBody className="flex min-h-0 flex-1 flex-col gap-5 overflow-hidden py-5">
                    <VoteBoard
                        tallies={voting.tallies}
                        members={anonymous ? undefined : (voting.members ?? [])}
                        anonymous={anonymous}
                        awaitingCount={anonymous ? (voting.awaiting_count ?? 0) : undefined}
                        awaitingLabel={closed ? t('sessions.vote_did_not_vote') : undefined}
                        display
                        className="min-h-0 flex-1"
                    />
                    <VoteTotal tallies={voting.tallies} expected={expected || undefined} display />
                </PanelBody>
            </Panel>

            {closed ? null : <VotingBindingBanner voting={voting} t={t} />}

            {measure !== null ? (
                <p className="flex shrink-0 items-center gap-2 px-1 text-[clamp(0.8125rem,1vw,1.125rem)] text-ink-muted">
                    <FileText aria-hidden="true" strokeWidth={1.75} className="size-4 shrink-0" />
                    <span className="truncate">{t('sessions.hall.measure_on_file', { title: measure })}</span>
                </p>
            ) : null}
        </div>
    );
}

/** The one clock on the board. Recess remaining, or Recess ended until the clerk resumes. */
function RecessStage({
    remainingFromServer,
    controls,
    t,
}: {
    remainingFromServer: number;
    controls: ReactNode;
    t: Translate;
}) {
    const remaining = useRecessRemaining(remainingFromServer);
    const ended = remaining === 0;

    return (
        <section className="relative flex min-h-[18rem] flex-1 flex-col justify-center overflow-hidden bg-floor-plate px-5 py-8 text-floor-ink md:px-7">
            <div className="absolute inset-x-5 top-4 flex justify-end md:inset-x-7">{controls}</div>
            <p className="text-eyebrow text-floor-ink-faint">
                {ended ? t('sessions.recess_ended') : t('sessions.action_recess')}
            </p>
            <p
                className="mt-6 font-mono font-semibold tracking-[-0.06em] text-floor-ink tabular-nums text-[clamp(2.75rem,12vw,9rem)] leading-none"
                aria-live="polite"
            >
                {formatClockCountdown(remaining)}
            </p>
            <p className="mt-6 max-w-[40ch] text-[clamp(1rem,1.5vw,1.75rem)] leading-relaxed text-floor-ink-muted">
                {ended ? t('sessions.recess_ended_hint') : t('sessions.recess_until_resume')}
            </p>
        </section>
    );
}

/** A control sitting on the navy plate, where the usual button tones cannot go. */
function PlateButton({
    onClick,
    icon: Icon,
    label,
    iconOnly = false,
}: {
    onClick: () => void;
    icon: typeof Maximize2;
    label: string;
    iconOnly?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={iconOnly ? label : undefined}
            title={iconOnly ? label : undefined}
            className={cn(
                'inline-flex min-h-9 items-center gap-1.5 rounded-full border border-floor-line bg-floor-sunk px-3 text-xs font-semibold text-floor-ink-muted transition-colors hover:text-floor-ink',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-focus)]',
            )}
        >
            <Icon aria-hidden="true" strokeWidth={2} className="size-4 shrink-0" />
            {iconOnly ? null : <span className="hidden sm:inline">{label}</span>}
        </button>
    );
}

function formatWallClock(now: number | null): string | null {
    if (now === null) {
        return null;
    }

    return new Date(now).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
}

/**
 * Full-screen chamber mode, plus the idle timer that withdraws the projector
 * control once the room is left alone with the board.
 */
function usePresentation() {
    const [presenting, setPresenting] = useState(false);
    const [idle, setIdle] = useState(false);

    // Requested straight from the click: the fullscreen API wants a live user
    // gesture, and an effect scheduled after paint no longer counts as one.
    const enter = useCallback(() => {
        setPresenting(true);
        setIdle(false);
        void document.documentElement.requestFullscreen?.().catch(() => undefined);
    }, []);

    const leave = useCallback(() => {
        setPresenting(false);
        setIdle(false);

        if (document.fullscreenElement) {
            void document.exitFullscreen().catch(() => undefined);
        }
    }, []);

    useEffect(() => {
        function onFullscreenChange() {
            if (!document.fullscreenElement) {
                setPresenting(false);
                setIdle(false);
            }
        }

        document.addEventListener('fullscreenchange', onFullscreenChange);

        return () => document.removeEventListener('fullscreenchange', onFullscreenChange);
    }, []);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            const target = event.target as HTMLElement | null;

            if (target?.isContentEditable || (target && /^(input|textarea|select)$/i.test(target.tagName))) {
                return;
            }

            if (event.key === 'f' || event.key === 'F') {
                event.preventDefault();

                if (presenting) {
                    leave();
                } else {
                    enter();
                }

                return;
            }

            if (event.key === 'Escape' && presenting) {
                leave();
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [presenting, enter, leave]);

    useEffect(() => {
        let timer = window.setTimeout(() => setIdle(true), IDLE_MS);

        function wake() {
            setIdle(false);
            window.clearTimeout(timer);
            timer = window.setTimeout(() => setIdle(true), IDLE_MS);
        }

        window.addEventListener('mousemove', wake);
        window.addEventListener('keydown', wake);
        window.addEventListener('touchstart', wake);

        return () => {
            window.clearTimeout(timer);
            window.removeEventListener('mousemove', wake);
            window.removeEventListener('keydown', wake);
            window.removeEventListener('touchstart', wake);
        };
    }, []);

    return { presenting, idle, enter, leave };
}
