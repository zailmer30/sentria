import { MemberActionRail } from '@/components/session/MemberActionRail';
import { MemberAgendaRail } from '@/components/session/MemberAgendaRail';
import { FloorRecognitionDock, MemberRaiseMotionButton } from '@/components/session/FloorRecognitionDock';
import { MemberReadingPane } from '@/components/session/MemberReadingPane';
import { MinutesCorrectionsPanel } from '@/components/session/MinutesCorrectionsPanel';
import { SessionAssistantPanel, type SessionAssistantData } from '@/components/session/SessionAssistantPanel';
import { VoteBoard } from '@/components/session/VoteBoard';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useSessionEcho } from '@/hooks/useSessionEcho';
import SessionLayout from '@/layouts/SessionLayout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { MemberVotePanel, type FloorProps, type ReadingPackItem } from '@/pages/Sessions/Floor/shared';
import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { List, StickyNote } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type BoardMemberFloorProps = FloorProps & {
    assistant?: SessionAssistantData | null;
    reading_pack?: ReadingPackItem[];
};

const MEMBER_ECHO_PROPS = [
    'session',
    'current_item',
    'next_item',
    'quorum',
    'attendance',
    'voting',
    'motions',
    'recognition',
    'elapsed_seconds',
    'assistant',
    'document_link',
    'reading_pack',
    'private_notes',
    'minutes_corrections',
];

function initialSelectedId(
    pack: ReadingPackItem[],
    currentId: string | null,
    queryItem: string | null,
): string | null {
    if (queryItem && pack.some((item) => item.id === queryItem)) {
        return queryItem;
    }

    if (currentId && pack.some((item) => item.id === currentId)) {
        return currentId;
    }

    return pack[0]?.id ?? null;
}

export default function BoardMemberFloor({
    session,
    current_item,
    reading_pack = [],
    private_notes,
    voting,
    quorum,
    can,
    recognition = { pending: [], recognized: null },
    assistant = null,
    minutes_corrections = [],
}: BoardMemberFloorProps) {
    const { t } = useTranslations();
    const { auth } = usePage<PageProps>().props;
    useSessionEcho(session.id, MEMBER_ECHO_PROPS);

    const queryItem =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search).get('item')
            : null;

    const currentId = current_item?.id ?? null;
    const [followingFloor, setFollowingFloor] = useState(() => {
        if (queryItem && queryItem !== currentId) {
            return false;
        }

        return true;
    });
    const [selectedId, setSelectedId] = useState<string | null>(() =>
        initialSelectedId(reading_pack, currentId, queryItem),
    );
    const [agendaOpen, setAgendaOpen] = useState(false);
    const [notesOpen, setNotesOpen] = useState(false);
    const [notesRailOpen, setNotesRailOpen] = useState(true);
    const [assistantOpen, setAssistantOpen] = useState(false);

    useEffect(() => {
        if (window.localStorage.getItem('sentria.floor.notes-rail') === '0') {
            setNotesRailOpen(false);
        }
    }, []);

    useEffect(() => {
        if (followingFloor && currentId) {
            setSelectedId(currentId);
        }
    }, [currentId, followingFloor]);

    useEffect(() => {
        if (selectedId && !reading_pack.some((item) => item.id === selectedId)) {
            setSelectedId(initialSelectedId(reading_pack, currentId, null));
        }
    }, [reading_pack, selectedId, currentId]);

    const selectedItem = useMemo(
        () => reading_pack.find((item) => item.id === selectedId) ?? null,
        [reading_pack, selectedId],
    );

    function selectItem(itemId: string) {
        setSelectedId(itemId);
        setFollowingFloor(itemId === currentId);
        setAgendaOpen(false);

        const url = new URL(window.location.href);

        if (itemId === currentId) {
            url.searchParams.delete('item');
        } else {
            url.searchParams.set('item', itemId);
        }

        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
    }

    function followFloor() {
        setFollowingFloor(true);

        if (currentId) {
            setSelectedId(currentId);
        }

        const url = new URL(window.location.href);
        url.searchParams.delete('item');
        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
        setAgendaOpen(false);
    }

    function setNotesRail(open: boolean) {
        setNotesRailOpen(open);
        window.localStorage.setItem('sentria.floor.notes-rail', open ? '1' : '0');
    }

    const showBallot = Boolean(can.cast_vote && current_item && voting.open && selectedId === currentId);
    const canSeeNamedRoll = Boolean(can.open_voting || can.close_voting);
    const expectedBallots =
        voting.members?.filter((member) => member.status === 'present' || member.status === 'late').length ||
        quorum.present_count;
    const canSeek = Boolean(can.seek_recognition);
    const ownPending = recognition.pending.find((row) => row.user_id === auth.user?.id) ?? null;
    const raiseButton = (
        <MemberRaiseMotionButton
            sessionId={session.id}
            canSeek={canSeek}
            pendingRequest={ownPending}
        />
    );

    return (
        <SessionLayout
            title={t('sessions.floor.member')}
            sessionTitle={session.title}
            sessionId={session.id}
            sessionStatus={session.status}
            venue={session.venue}
            presidingOfficer={session.presiding_officer}
            variant="workstation"
        >
            <div className="flex min-h-0 flex-1 flex-col">
                <FloorRecognitionDock
                    sessionId={session.id}
                    recognition={recognition}
                    className="mx-4 mt-3 md:mx-6"
                />
                <div className="flex shrink-0 items-center gap-2 px-4 py-3 xl:hidden">
                    <Button type="button" variant="secondary" size="sm" onClick={() => setAgendaOpen(true)}>
                        <List aria-hidden className="size-4" strokeWidth={1.75} />
                        {t('sessions.agenda')}
                    </Button>
                    {raiseButton}
                    <Button type="button" variant="secondary" size="sm" onClick={() => setNotesOpen(true)}>
                        <StickyNote aria-hidden className="size-4" strokeWidth={1.75} />
                        {t('sessions.my_notes')}
                    </Button>
                </div>

                <div
                    className={cn(
                        'grid min-h-0 flex-1 grid-cols-1 gap-4 overflow-hidden px-4 pb-4 md:px-6 xl:pt-4',
                        notesRailOpen
                            ? 'xl:grid-cols-[16.5rem_minmax(0,1fr)_20rem]'
                            : 'xl:grid-cols-[16.5rem_minmax(0,1fr)]',
                    )}
                >
                    <MemberAgendaRail
                        className="hidden min-h-0 xl:flex"
                        items={reading_pack}
                        selectedId={selectedId}
                        currentItemId={currentId}
                        followingFloor={followingFloor}
                        onSelect={selectItem}
                        onFollowFloor={followFloor}
                    />

                    <div className="flex min-h-0 min-w-0 flex-col gap-4 overflow-hidden">
                        <MemberReadingPane
                            key={selectedItem?.id ?? 'none'}
                            item={selectedItem}
                            currentItemId={currentId}
                            followingFloor={followingFloor}
                            onFollowFloor={followFloor}
                            headerActions={
                                <>
                                    <MemberRaiseMotionButton
                                        sessionId={session.id}
                                        canSeek={canSeek}
                                        pendingRequest={ownPending}
                                        className="hidden border-floor-line bg-floor-sunk text-floor-ink hover:bg-floor-sunk xl:inline-flex"
                                    />
                                    {notesRailOpen ? null : (
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="hidden border-floor-line bg-floor-sunk text-floor-ink hover:bg-floor-sunk xl:inline-flex"
                                            onClick={() => setNotesRail(true)}
                                            aria-expanded="false"
                                            aria-controls="member-notes-rail"
                                        >
                                            <StickyNote aria-hidden className="size-3.5" strokeWidth={1.75} />
                                            {t('sessions.my_notes')}
                                        </Button>
                                    )}
                                </>
                            }
                        />

                        {selectedItem?.category === 'approval-minutes' && selectedItem.document ? (
                            <MinutesCorrectionsPanel
                                sessionId={session.id}
                                agendaItemId={selectedItem.id}
                                corrections={minutes_corrections}
                                t={t}
                                compact
                                className="shrink-0"
                            />
                        ) : null}

                        {showBallot ? (
                            <div className="flex min-h-0 shrink-0 flex-col gap-4">
                                <MemberVotePanel
                                    sessionId={session.id}
                                    currentItem={current_item}
                                    voting={voting}
                                    can={can}
                                    t={t}
                                    variant="ballot"
                                    expectedBallots={expectedBallots}
                                />
                                {canSeeNamedRoll ? (
                                    <VoteBoard tallies={voting.tallies} members={voting.members ?? []} floor />
                                ) : voting.silent ? (
                                    <VoteBoard
                                        tallies={voting.tallies}
                                        anonymous
                                        awaitingCount={voting.awaiting_count ?? 0}
                                        floor
                                    />
                                ) : null}
                            </div>
                        ) : null}
                    </div>

                    <MemberActionRail
                        className={cn('min-h-0', notesRailOpen ? 'hidden xl:flex' : 'hidden')}
                        sessionId={session.id}
                        selectedItem={selectedItem}
                        privateNotes={private_notes}
                        canUseAssistant={Boolean(can.use_assistant)}
                        onOpenAssistant={() => setAssistantOpen(true)}
                        onHide={() => setNotesRail(false)}
                    />
                </div>
            </div>

            <Sheet open={agendaOpen} onOpenChange={setAgendaOpen}>
                <SheetContent side="left" className="w-[min(20rem,85vw)] p-0" closeLabel={t('sessions.assistant.close')}>
                    <SheetHeader className="sr-only">
                        <SheetTitle>{t('sessions.agenda')}</SheetTitle>
                        <SheetDescription>{t('sessions.reading.agenda_sheet_hint')}</SheetDescription>
                    </SheetHeader>
                    <MemberAgendaRail
                        className="rounded-none border-0 shadow-none"
                        items={reading_pack}
                        selectedId={selectedId}
                        currentItemId={currentId}
                        followingFloor={followingFloor}
                        onSelect={selectItem}
                        onFollowFloor={followFloor}
                    />
                </SheetContent>
            </Sheet>

            <Sheet open={notesOpen} onOpenChange={setNotesOpen}>
                <SheetContent side="right" className="w-[min(22rem,90vw)] p-0" closeLabel={t('sessions.assistant.close')}>
                    <SheetHeader className="sr-only">
                        <SheetTitle>{t('sessions.my_notes')}</SheetTitle>
                        <SheetDescription>{t('sessions.reading.notes_sheet_hint')}</SheetDescription>
                    </SheetHeader>
                    <MemberActionRail
                        className="h-full rounded-none p-3"
                        sessionId={session.id}
                        selectedItem={selectedItem}
                        privateNotes={private_notes}
                        canUseAssistant={Boolean(can.use_assistant)}
                        onOpenAssistant={() => {
                            setNotesOpen(false);
                            setAssistantOpen(true);
                        }}
                    />
                </SheetContent>
            </Sheet>

            <SessionAssistantPanel
                sessionId={session.id}
                assistant={assistant}
                canUseAssistant={Boolean(can.use_assistant)}
                hideToggle={false}
                toggleClassName={notesRailOpen ? 'xl:hidden' : undefined}
                open={assistantOpen}
                onOpenChange={setAssistantOpen}
            />
        </SessionLayout>
    );
}
