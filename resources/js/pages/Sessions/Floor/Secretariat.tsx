import { FloorRecognitionDock } from '@/components/session/FloorRecognitionDock';
import { CalendarDocket, CalendarItemActions } from '@/components/session/CalendarDocket';
import { OrderOfBusiness } from '@/components/session/OrderOfBusiness';
import { SecretariatMinutesPanel } from '@/components/session/SecretariatMinutesPanel';
import { SessionAssistantPanel } from '@/components/session/SessionAssistantPanel';
import { VotingMemberBoard } from '@/components/session/VotingMemberBoard';
import { useSessionEcho } from '@/hooks/useSessionEcho';
import { useTranscriptEcho } from '@/hooks/useTranscriptEcho';
import SessionLayout from '@/layouts/SessionLayout';
import { useTranslations } from '@/lib/i18n';
import { useMemo } from 'react';
import {
    ConsoleAgendaCard,
    ConsoleAttendance,
    ConsoleControls,
    ConsoleHallDocument,
    ConsoleMotions,
    ConsolePlate,
    ConsoleRecording,
    ConsoleTranscript,
} from './console';
import { MotionRecorder, VotingBindingBanner, type FloorProps } from './shared';

/**
 * The secretariat console. The sitting's identity and its three counted facts
 * sit on the plate at the top. Below that the clerk switches between the
 * console (agenda, controls, the discussion inbox, motions) and the minutes
 * tab — a live record that is folded into the system-generated draft after
 * adjournment.
 *
 * The layout chrome above deliberately runs without the session title here —
 * the plate owns the sitting's identity on this surface, and stating it twice
 * within one screen height reads as two different headings.
 */

const SECRETARIAT_ECHO_PROPS = [
    'session',
    'current_item',
    'next_item',
    'previous_item',
    'quorum',
    'attendance',
    'voting',
    'motions',
    'recognition',
    'elapsed_seconds',
    'assistant',
    'transcript',
    'hall_display',
    'reading_pack',
    'calendar_docket',
    'advance_blocked_reason',
];

export default function SecretariatFloor({
    session,
    current_item,
    next_item,
    previous_item = null,
    quorum,
    attendance,
    elapsed_seconds,
    can,
    voting,
    motions,
    recognition = { pending: [], recognized: null },
    reading_pack = [],
    calendar_docket = [],
    hall_display = { stage: 'item', agenda_item_id: null },
    assistant = null,
    transcript = null,
    committees = [],
    advance_blocked_reason = null,
    workspace = 'console',
}: FloorProps) {
    const { t } = useTranslations();
    useSessionEcho(session.id, SECRETARIAT_ECHO_PROPS);

    const { segments: liveSegments, status: liveStatus, error: liveError } = useTranscriptEcho(
        session.id,
        transcript?.segments ?? [],
        Boolean(can.view_transcript),
        {
            status: transcript?.status,
            error: transcript?.processing_error,
        },
    );

    const segments = liveSegments.length > 0 ? liveSegments : (transcript?.segments ?? []);

    const present = attendance.filter((row) => row.status === 'present' || row.status === 'late').length;
    const absent = attendance.filter((row) => row.status === 'absent').length;

    const currentPackItem = useMemo(
        () => reading_pack.find((row) => row.id === current_item?.id) ?? null,
        [reading_pack, current_item?.id],
    );

    const projectedPackItem = useMemo(() => {
        if (hall_display.stage !== 'document' || !hall_display.agenda_item_id) {
            return null;
        }

        return reading_pack.find((row) => row.id === hall_display.agenda_item_id) ?? null;
    }, [hall_display, reading_pack]);

    return (
        <SessionLayout
            title={workspace === 'minutes' ? t('sessions.floor.tab_minutes') : t('sessions.floor.secretariat')}
            sessionId={session.id}
        >
            <div className="flex flex-col gap-4">
                <FloorRecognitionDock sessionId={session.id} recognition={recognition} />

                <ConsolePlate
                    session={session}
                    role={t('sessions.floor.secretariat')}
                    quorum={quorum}
                    elapsedSeconds={elapsed_seconds}
                    voting={voting}
                    expectedBallots={present}
                    t={t}
                />

                <VotingBindingBanner voting={voting} t={t} />

                {workspace === 'minutes' ? (
                    <SecretariatMinutesPanel
                        sessionId={session.id}
                        value={session.secretariat_minutes}
                        canRecord={Boolean(can.record_minutes)}
                        currentItem={current_item}
                        t={t}
                    />
                ) : (
                    <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                        <div className="flex flex-col gap-4">
                                <ConsoleAgendaCard
                                    sessionId={session.id}
                                    item={current_item}
                                    packItem={currentPackItem}
                                    voting={voting}
                                    hallDisplay={hall_display}
                                    canControlHall={Boolean(can.control_hall_display)}
                                    committees={committees}
                                    t={t}
                                    actions={
                                        current_item ? (
                                            <CalendarItemActions
                                                sessionId={session.id}
                                                item={{
                                                    id: current_item.id,
                                                    can_second_reading:
                                                        currentPackItem?.can_second_reading ?? current_item.can_second_reading,
                                                    can_third_reading:
                                                        currentPackItem?.can_third_reading ?? current_item.can_third_reading,
                                                    placed_on_third_reading:
                                                        currentPackItem?.placed_on_third_reading ??
                                                        current_item.placed_on_third_reading,
                                                    can_postpone: currentPackItem?.can_postpone ?? current_item.can_postpone,
                                                    can_undo: currentPackItem?.can_undo ?? current_item.can_undo,
                                                }}
                                                t={t}
                                            />
                                        ) : null
                                    }
                                />

                                <CalendarDocket sessionId={session.id} items={calendar_docket} t={t} />

                                {projectedPackItem ? (
                                    <ConsoleHallDocument
                                        sessionId={session.id}
                                        packItem={projectedPackItem}
                                        view={hall_display.view ?? null}
                                        t={t}
                                    />
                                ) : null}

                                <ConsoleControls
                                    session={session}
                                    currentItem={current_item}
                                    nextItem={next_item}
                                    previousItem={previous_item}
                                    currentPackItem={currentPackItem}
                                    can={can}
                                    voting={voting}
                                    hallDisplay={hall_display}
                                    expectedBallots={present}
                                    t={t}
                                    advanceBlockedReason={advance_blocked_reason}
                                />

                                {can.view_transcript ? (
                                    <ConsoleTranscript
                                        sessionId={session.id}
                                        transcriptId={transcript?.id ?? null}
                                        segments={segments}
                                        status={liveStatus ?? transcript?.status ?? null}
                                        error={liveError ?? transcript?.processing_error ?? null}
                                        speakers={attendance
                                            .filter((row) => Boolean(row.user_id))
                                            .map((row) => ({
                                                id: row.user_id as string,
                                                display_name: row.display_name,
                                            }))}
                                        canCorrect={Boolean(can.correct_transcript)}
                                        t={t}
                                    />
                                ) : null}

                                {voting.open ? (
                                    <VotingMemberBoard
                                        members={voting.members ?? []}
                                        presentCount={present}
                                        absentCount={absent}
                                    />
                                ) : null}

                                <MotionRecorder
                                    key={recognition.recognized?.id ?? 'motion-recorder'}
                                    sessionId={session.id}
                                    currentItemId={current_item?.id ?? null}
                                    canCreate={Boolean(can.create_motion || can.record_spoken_motion)}
                                    t={t}
                                    recognized={recognition.recognized}
                                />

                                <ConsoleMotions motions={motions} sessionId={session.id} t={t} />
                            </div>

                            <aside className="flex flex-col gap-4 xl:sticky xl:top-32 xl:self-start">
                                {can.manage_recording ? <ConsoleRecording session={session} t={t} /> : null}

                                <ConsoleAttendance attendance={attendance} quorum={quorum} t={t} />

                                <OrderOfBusiness items={reading_pack} currentItemId={current_item?.id ?? null} />
                            </aside>
                        </div>
                )}
            </div>

            <SessionAssistantPanel
                sessionId={session.id}
                assistant={assistant ?? null}
                canUseAssistant={Boolean(can.use_assistant)}
            />
        </SessionLayout>
    );
}
