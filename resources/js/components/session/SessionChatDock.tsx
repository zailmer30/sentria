import { SessionFloorFab } from '@/components/session/SessionFloorTools';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input, Textarea } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import {
    getEcho,
    subscribeToSessionChatInbox,
    subscribeToSessionChatThread,
    type SessionChatConversation,
    type SessionChatMessage,
    type SessionChatParticipant,
    type SessionChatPerson,
} from '@/lib/echo';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { ArrowLeft, MessageSquare, Plus, Send, Users } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

type DirectoryPayload = {
    secretary_id: string | null;
    voting_open: boolean;
    clerks: SessionChatPerson[];
    members: SessionChatPerson[];
};

type InboxPayload = {
    voting_open: boolean;
    unread_total: number;
    conversations: SessionChatConversation[];
};

type View = 'inbox' | 'compose-direct' | 'compose-group' | 'thread';

type SessionChatDockProps = {
    sessionId: string;
    sessionStatus?: string;
    size?: 'default' | 'floor';
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function latestSeenOwnMessageId(
    messages: SessionChatMessage[],
    participants: SessionChatParticipant[],
    viewerId: string,
): string | null {
    const others = participants.filter((person) => person.id !== viewerId);

    if (others.length === 0) {
        return null;
    }

    const own = [...messages]
        .filter((message) => message.user_id === viewerId && message.created_at)
        .sort((left, right) => (right.created_at ?? '').localeCompare(left.created_at ?? ''));

    for (const message of own) {
        const created = message.created_at;

        if (!created) {
            continue;
        }

        const seen = others.every((person) => Boolean(person.last_read_at && person.last_read_at >= created));

        if (seen) {
            return message.id;
        }
    }

    return null;
}

function ThreadMessages({
    messages,
    participants,
    viewerId,
    formatTime,
    youLabel,
    seenLabel,
}: {
    messages: SessionChatMessage[];
    participants: SessionChatParticipant[];
    viewerId: string;
    formatTime: (value: string | null | undefined) => string;
    youLabel: string;
    seenLabel: string;
}) {
    const seenId = latestSeenOwnMessageId(messages, participants, viewerId);

    return (
        <ul className="flex flex-col gap-3">
            {messages.map((message) => {
                const mine = message.user_id === viewerId;
                const isLatestSeen = mine && seenId === message.id;

                return (
                    <li key={message.id} className={cn('flex flex-col gap-0.5', mine ? 'items-end' : 'items-start')}>
                        <span className="text-2xs text-ink-subtle">{mine ? youLabel : message.display_name}</span>
                        <span
                            className={cn(
                                'max-w-[85%] px-3.5 py-2 text-sm leading-5 wrap-break-word',
                                mine
                                    ? 'rounded-[var(--radius-xl)] rounded-br-[var(--radius-xs)] bg-accent text-[var(--color-accent-on)]'
                                    : 'rounded-[var(--radius-xl)] rounded-bl-[var(--radius-xs)] bg-canvas-sunk text-ink',
                            )}
                        >
                            {message.body}
                        </span>
                        <span className={cn('flex max-w-[85%] flex-col', mine ? 'items-end' : 'items-start')}>
                            {message.created_at ? (
                                <time dateTime={message.created_at} className="text-2xs text-ink-faint">
                                    {formatTime(message.created_at)}
                                </time>
                            ) : null}
                            {isLatestSeen ? <span className="text-2xs text-ink-muted">{seenLabel}</span> : null}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

async function requestJson<T>(url: string, init?: RequestInit): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
            ...(init?.headers ?? {}),
        },
    });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return (await response.json()) as T;
}

export function SessionChatDock({ sessionId, sessionStatus, size = 'default' }: SessionChatDockProps) {
    const { t } = useTranslations();
    const { formatTime } = useFormatters();
    const { auth } = usePage<PageProps>().props;
    const pageUrl = usePage().url;
    const user = auth.user;
    const path = pageUrl.split('?')[0] ?? pageUrl;
    const isCapture = /\/sessions\/[^/]+\/capture$/.test(path);
    const live = sessionStatus === 'in-session' || sessionStatus === 'suspended';
    const canUse = Boolean(user?.permissions.includes('session-chat.use'));

    const [open, setOpen] = useState(false);
    const [view, setView] = useState<View>('inbox');
    const [conversations, setConversations] = useState<SessionChatConversation[]>([]);
    const [unreadTotal, setUnreadTotal] = useState(0);
    const [votingOpen, setVotingOpen] = useState(false);
    const [directory, setDirectory] = useState<DirectoryPayload | null>(null);
    const [active, setActive] = useState<SessionChatConversation | null>(null);
    const [draft, setDraft] = useState('');
    const [groupName, setGroupName] = useState('');
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const threadEnd = useRef<HTMLDivElement | null>(null);
    const openRef = useRef(false);
    const activeIdRef = useRef<string | null>(null);
    const votingOpenRef = useRef(false);

    const eligible = Boolean(sessionId && live && canUse && !isCapture && user);

    const loadInbox = useCallback(async (): Promise<InboxPayload | null> => {
        try {
            const payload = await requestJson<InboxPayload>(`/sessions/${sessionId}/chat`);
            setConversations(payload.conversations);
            setUnreadTotal(payload.unread_total);
            setVotingOpen(payload.voting_open);
            setError(null);

            return payload;
        } catch {
            setError('sessions.chat.error_load');

            return null;
        }
    }, [sessionId]);

    const openThread = useCallback(
        async (conversationId: string) => {
            setBusy(true);
            setError(null);

            try {
                const payload = await requestJson<SessionChatConversation>(`/sessions/${sessionId}/chat/${conversationId}`);
                setActive(payload);
                setView('thread');
                setDraft('');
                await requestJson(`/sessions/${sessionId}/chat/${conversationId}/read`, {
                    method: 'POST',
                    body: '{}',
                });
                await loadInbox();
            } catch {
                setError('sessions.chat.error_load');
            } finally {
                setBusy(false);
            }
        },
        [loadInbox, sessionId],
    );

    useEffect(() => {
        openRef.current = open;
    }, [open]);

    useEffect(() => {
        activeIdRef.current = active?.id ?? null;
    }, [active?.id]);

    useEffect(() => {
        votingOpenRef.current = votingOpen;
    }, [votingOpen]);

    useEffect(() => {
        if (!eligible || !user) {
            return;
        }

        let cancelled = false;

        void requestJson<InboxPayload>(`/sessions/${sessionId}/chat`)
            .then((payload) => {
                if (cancelled) {
                    return;
                }

                setConversations(payload.conversations);
                setUnreadTotal(payload.unread_total);
                setVotingOpen(payload.voting_open);
            })
            .catch(() => {
                if (!cancelled) {
                    setError('sessions.chat.error_load');
                }
            });

        const unsubscribeInbox = subscribeToSessionChatInbox(user.id, (payload) => {
            if (payload.session_id !== sessionId) {
                return;
            }

            void loadInbox().then((inbox) => {
                if (!inbox || votingOpenRef.current || openRef.current) {
                    return;
                }

                const incoming = inbox.conversations.find((row) => row.id === payload.conversation_id);
                const preview = incoming?.last_message?.body?.trim();

                if (incoming && preview) {
                    toast.message(incoming.title || t('sessions.chat.title'), {
                        description: preview.length > 80 ? `${preview.slice(0, 79)}…` : preview,
                    });
                }
            });
        });

        const echo = getEcho();
        const sessionChannel = echo?.private(`session.${sessionId}`);
        const onVoteOpen = () => setVotingOpen(true);
        const onVoteClose = () => setVotingOpen(false);

        sessionChannel?.listen('.VotingOpened', onVoteOpen);
        sessionChannel?.listen('.VotingClosed', onVoteClose);

        return () => {
            cancelled = true;
            unsubscribeInbox();
            sessionChannel?.stopListening('.VotingOpened', onVoteOpen);
            sessionChannel?.stopListening('.VotingClosed', onVoteClose);
        };
    }, [eligible, loadInbox, sessionId, t, user]);

    const conversationIds = useMemo(() => conversations.map((row) => row.id).join(','), [conversations]);

    useEffect(() => {
        if (!eligible || conversationIds === '') {
            return;
        }

        const ids = conversationIds.split(',');
        const unsubscribers = ids.map((id) =>
            subscribeToSessionChatThread(id, {
                onMessage: ({ conversation_id, message }) => {
                    setConversations((current) =>
                        current.map((row) =>
                            row.id === conversation_id
                                ? {
                                      ...row,
                                      last_message: {
                                          id: message.id,
                                          user_id: message.user_id,
                                          body: message.body,
                                          created_at: message.created_at,
                                      },
                                      last_message_at: message.created_at,
                                      unread_count:
                                          conversation_id === activeIdRef.current || message.user_id === user?.id
                                              ? row.unread_count
                                              : row.unread_count + 1,
                                  }
                                : row,
                        ),
                    );

                    if (message.user_id !== user?.id && conversation_id !== activeIdRef.current) {
                        setUnreadTotal((count) => count + 1);
                    }

                    if (message.user_id !== user?.id && conversation_id === activeIdRef.current) {
                        void requestJson(`/sessions/${sessionId}/chat/${conversation_id}/read`, {
                            method: 'POST',
                            body: '{}',
                        });
                    }

                    setActive((current) => {
                        if (current?.id !== conversation_id) {
                            return current;
                        }

                        if (current.messages?.some((row) => row.id === message.id)) {
                            return current;
                        }

                        return {
                            ...current,
                            messages: [...(current.messages ?? []), message],
                        };
                    });
                },
                onRead: ({ conversation_id, user_id, last_read_at }) => {
                    setActive((current) => {
                        if (current?.id !== conversation_id) {
                            return current;
                        }

                        return {
                            ...current,
                            participants: current.participants.map((person) =>
                                person.id === user_id ? { ...person, last_read_at } : person,
                            ),
                        };
                    });
                },
            }),
        );

        return () => {
            unsubscribers.forEach((stop) => stop());
        };
    }, [conversationIds, eligible, sessionId, user?.id]);

    useEffect(() => {
        threadEnd.current?.scrollIntoView({ block: 'end' });
    }, [active?.messages, view]);

    if (!eligible) {
        return null;
    }

    const people = [...(directory?.clerks ?? []), ...(directory?.members ?? [])];

    async function ensureDirectory(): Promise<DirectoryPayload | null> {
        if (directory) {
            return directory;
        }

        try {
            const payload = await requestJson<DirectoryPayload>(`/sessions/${sessionId}/chat/directory`);
            setDirectory(payload);
            setVotingOpen(payload.voting_open);

            return payload;
        } catch {
            setError('sessions.chat.error_load');

            return null;
        }
    }

    async function startDirect(userId: string): Promise<void> {
        setBusy(true);
        setError(null);

        try {
            const payload = await requestJson<SessionChatConversation>(`/sessions/${sessionId}/chat/direct`, {
                method: 'POST',
                body: JSON.stringify({ user_id: userId }),
            });
            await loadInbox();
            setActive(payload);
            setView('thread');
        } catch {
            setError('sessions.chat.error_start');
        } finally {
            setBusy(false);
        }
    }

    async function startGroup(event: FormEvent): Promise<void> {
        event.preventDefault();

        if (selectedIds.length < 2 || groupName.trim() === '') {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const payload = await requestJson<SessionChatConversation>(`/sessions/${sessionId}/chat/groups`, {
                method: 'POST',
                body: JSON.stringify({ name: groupName.trim(), participant_ids: selectedIds }),
            });
            setGroupName('');
            setSelectedIds([]);
            await loadInbox();
            setActive(payload);
            setView('thread');
        } catch {
            setError('sessions.chat.error_group');
        } finally {
            setBusy(false);
        }
    }

    async function sendMessage(event: FormEvent): Promise<void> {
        event.preventDefault();

        const body = draft.trim();

        if (!active || body === '') {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const message = await requestJson<SessionChatMessage>(`/sessions/${sessionId}/chat/${active.id}/messages`, {
                method: 'POST',
                body: JSON.stringify({ body }),
            });
            setDraft('');
            setActive((current) =>
                current
                    ? {
                          ...current,
                          messages: current.messages?.some((row) => row.id === message.id)
                              ? current.messages
                              : [...(current.messages ?? []), message],
                      }
                    : current,
            );
        } catch {
            setError('sessions.chat.error_send');
        } finally {
            setBusy(false);
        }
    }

    async function leaveGroup(): Promise<void> {
        if (!active || !user) {
            return;
        }

        setBusy(true);

        try {
            await fetch(`/sessions/${sessionId}/chat/${active.id}/participants/${user.id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            setActive(null);
            setView('inbox');
            await loadInbox();
        } finally {
            setBusy(false);
        }
    }

    function toggleSelected(id: string): void {
        setSelectedIds((current) => (current.includes(id) ? current.filter((row) => row !== id) : [...current, id]));
    }

    const heading =
        view === 'thread'
            ? (active?.title ?? t('sessions.chat.title'))
            : view === 'compose-group'
              ? t('sessions.chat.new_group')
              : view === 'compose-direct'
                ? t('sessions.chat.new_direct')
                : t('sessions.chat.title');

    return (
        <>
            {!open ? (
                <SessionFloorFab>
                    <Button
                        type="button"
                        variant="primary"
                        size={size === 'floor' ? 'icon-floor' : 'icon'}
                        aria-label={t('sessions.chat.open')}
                        title={t('sessions.chat.open')}
                        className="relative size-14 rounded-full shadow-[var(--shadow-lg)] [&_svg]:size-5"
                        onClick={() => {
                            setOpen(true);
                            setView('inbox');
                            void loadInbox();
                        }}
                    >
                        <MessageSquare aria-hidden="true" strokeWidth={1.75} />
                        {unreadTotal > 0 ? (
                            <span className="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-live px-1 text-2xs font-semibold text-[var(--color-live-on)]">
                                {unreadTotal > 99 ? '99+' : unreadTotal}
                            </span>
                        ) : null}
                    </Button>
                </SessionFloorFab>
            ) : null}

            <Sheet
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (!next) {
                        setView('inbox');
                        setActive(null);
                        setError(null);
                    }
                }}
            >
                <SheetContent
                    side="bottom"
                    className="h-[min(38rem,82dvh)] w-full max-w-none overflow-hidden rounded-t-[var(--radius-xl)] border-x sm:inset-x-auto sm:right-5 sm:bottom-5 sm:left-auto sm:w-[min(26rem,calc(100vw-2.5rem))] sm:rounded-[var(--radius-xl)] sm:border"
                    closeLabel={t('sessions.chat.close')}
                >
                    <SheetHeader className="relative gap-1.5">
                        <div className="absolute top-1.5 left-1/2 h-1 w-10 -translate-x-1/2 rounded-full bg-line-strong sm:hidden" />
                        <div className="flex items-start gap-2 pr-8">
                            {view !== 'inbox' ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    className="mt-0.5"
                                    aria-label={t('sessions.chat.back')}
                                    onClick={() => {
                                        setView('inbox');
                                        setActive(null);
                                        void loadInbox();
                                    }}
                                >
                                    <ArrowLeft aria-hidden="true" strokeWidth={1.75} />
                                </Button>
                            ) : null}
                            <div className="min-w-0">
                                <SheetTitle>{heading}</SheetTitle>
                                <SheetDescription>
                                    {sessionStatus === 'suspended' ? t('sessions.chat.recess_hint') : t('sessions.chat.hint')}
                                </SheetDescription>
                            </div>
                        </div>
                    </SheetHeader>

                    {error ? (
                        <p className="px-4 pt-3 text-sm text-critical" role="alert">
                            {t(error)}
                        </p>
                    ) : null}

                    {view === 'inbox' ? (
                        <div className="flex min-h-0 flex-1 flex-col">
                            <div className="flex gap-2 border-b border-line px-4 py-3">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="flex-1"
                                    onClick={() => {
                                        setView('compose-direct');
                                        void ensureDirectory();
                                    }}
                                >
                                    <Plus aria-hidden="true" strokeWidth={1.75} className="size-4" />
                                    {t('sessions.chat.new_direct')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="flex-1"
                                    onClick={() => {
                                        setView('compose-group');
                                        setSelectedIds([]);
                                        void ensureDirectory();
                                    }}
                                >
                                    <Users aria-hidden="true" strokeWidth={1.75} className="size-4" />
                                    {t('sessions.chat.new_group')}
                                </Button>
                            </div>

                            <ul className="min-h-0 flex-1 overflow-y-auto">
                                {conversations.length === 0 ? (
                                    <li className="px-6 py-12 text-center text-sm text-ink-muted">{t('sessions.chat.empty')}</li>
                                ) : (
                                    conversations.map((row) => (
                                        <li key={row.id}>
                                            <button
                                                type="button"
                                                className="flex w-full flex-col gap-0.5 border-b border-line px-4 py-3 text-left hover:bg-canvas-sunk"
                                                onClick={() => void openThread(row.id)}
                                            >
                                                <span className="flex items-center justify-between gap-2">
                                                    <span className="truncate text-sm font-medium text-ink">{row.title}</span>
                                                    {row.unread_count > 0 ? (
                                                        <Badge variant="live">{row.unread_count}</Badge>
                                                    ) : null}
                                                </span>
                                                <span className="flex items-center justify-between gap-2 text-xs text-ink-muted">
                                                    <span className="truncate">
                                                        {row.last_message?.body ?? t('sessions.chat.no_messages')}
                                                    </span>
                                                    {row.last_message_at ? (
                                                        <time
                                                            dateTime={row.last_message_at}
                                                            className="shrink-0 text-2xs text-ink-faint"
                                                        >
                                                            {formatTime(row.last_message_at)}
                                                        </time>
                                                    ) : null}
                                                </span>
                                            </button>
                                        </li>
                                    ))
                                )}
                            </ul>
                        </div>
                    ) : null}

                    {view === 'compose-direct' ? (
                        <ul className="min-h-0 flex-1 overflow-y-auto">
                            {directory?.clerks.length ? (
                                <li className="px-4 pt-3 pb-1 text-2xs font-semibold tracking-wide text-ink-subtle uppercase">
                                    {t('sessions.chat.clerks')}
                                </li>
                            ) : null}
                            {(directory?.clerks ?? []).map((person) => (
                                <li key={person.id}>
                                    <button
                                        type="button"
                                        disabled={busy}
                                        className="flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm hover:bg-canvas-sunk disabled:opacity-50"
                                        onClick={() => void startDirect(person.id)}
                                    >
                                        <span>{person.display_name}</span>
                                        {person.is_designated_secretary ? <Badge>{t('sessions.chat.designated')}</Badge> : null}
                                    </button>
                                </li>
                            ))}
                            {directory?.members.length ? (
                                <li className="px-4 pt-3 pb-1 text-2xs font-semibold tracking-wide text-ink-subtle uppercase">
                                    {t('sessions.chat.members')}
                                </li>
                            ) : null}
                            {(directory?.members ?? []).map((person) => (
                                <li key={person.id}>
                                    <button
                                        type="button"
                                        disabled={busy}
                                        className="w-full px-4 py-3 text-left text-sm hover:bg-canvas-sunk disabled:opacity-50"
                                        onClick={() => void startDirect(person.id)}
                                    >
                                        {person.display_name}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : null}

                    {view === 'compose-group' ? (
                        <form className="flex min-h-0 flex-1 flex-col" onSubmit={(event) => void startGroup(event)}>
                            <div className="border-b border-line px-4 py-3">
                                <label className="text-xs font-medium text-ink-muted" htmlFor="session-chat-group-name">
                                    {t('sessions.chat.group_name')}
                                </label>
                                <Input
                                    id="session-chat-group-name"
                                    value={groupName}
                                    onChange={(event) => setGroupName(event.target.value)}
                                    className="mt-1.5"
                                    maxLength={80}
                                    required
                                />
                                <p className="mt-2 text-xs text-ink-muted">{t('sessions.chat.group_help')}</p>
                            </div>
                            <ul className="min-h-0 flex-1 overflow-y-auto">
                                {people.map((person) => (
                                    <li key={person.id}>
                                        <label className="flex cursor-pointer items-center gap-3 px-4 py-3 text-sm hover:bg-canvas-sunk">
                                            <input
                                                type="checkbox"
                                                className="size-4 accent-[var(--color-accent)]"
                                                checked={selectedIds.includes(person.id)}
                                                onChange={() => toggleSelected(person.id)}
                                            />
                                            <span className="min-w-0 flex-1 truncate">{person.display_name}</span>
                                            {person.is_clerk ? (
                                                <Badge variant="secondary">{t('sessions.chat.clerk')}</Badge>
                                            ) : null}
                                        </label>
                                    </li>
                                ))}
                            </ul>
                            <div className="border-t border-line p-4">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    className="w-full"
                                    disabled={busy || selectedIds.length < 2 || groupName.trim() === ''}
                                >
                                    {t('sessions.chat.create_group')}
                                </Button>
                            </div>
                        </form>
                    ) : null}

                    {view === 'thread' && active ? (
                        <div className="flex min-h-0 flex-1 flex-col">
                            <div className="flex items-center justify-between gap-2 border-b border-line px-4 py-2">
                                <p className="truncate text-xs text-ink-muted">
                                    {active.participants
                                        .map((row) => row.display_name)
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                                {active.type === 'group' ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() => void leaveGroup()}
                                    >
                                        {t('sessions.chat.leave')}
                                    </Button>
                                ) : null}
                            </div>
                            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto bg-canvas px-4 py-3">
                                <div className="mt-auto flex flex-col">
                                    {(active.messages ?? []).length === 0 ? (
                                        <p className="text-sm text-ink-muted">{t('sessions.chat.no_messages')}</p>
                                    ) : (
                                        <ThreadMessages
                                            messages={active.messages ?? []}
                                            participants={active.participants}
                                            viewerId={user?.id ?? ''}
                                            formatTime={formatTime}
                                            youLabel={t('sessions.chat.you')}
                                            seenLabel={t('sessions.chat.seen')}
                                        />
                                    )}
                                    <div ref={threadEnd} />
                                </div>
                            </div>
                            <form
                                className="flex items-end gap-2 border-t border-line bg-surface px-3 py-3"
                                onSubmit={(event) => void sendMessage(event)}
                            >
                                <Textarea
                                    value={draft}
                                    onChange={(event) => setDraft(event.target.value)}
                                    rows={1}
                                    maxLength={2000}
                                    placeholder={t('sessions.chat.placeholder')}
                                    aria-label={t('sessions.chat.placeholder')}
                                    className="max-h-24 min-h-11 flex-1 resize-none rounded-full px-4 py-2.5 leading-5"
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' && !event.shiftKey) {
                                            event.preventDefault();
                                            event.currentTarget.form?.requestSubmit();
                                        }
                                    }}
                                />
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="icon-floor"
                                    className="rounded-full"
                                    disabled={busy || draft.trim() === ''}
                                    aria-label={t('sessions.chat.send')}
                                >
                                    <Send aria-hidden="true" strokeWidth={1.75} />
                                </Button>
                            </form>
                        </div>
                    ) : null}
                </SheetContent>
            </Sheet>
        </>
    );
}
