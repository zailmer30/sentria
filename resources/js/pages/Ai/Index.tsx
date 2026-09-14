import { AiContent } from '@/components/ai/AiContent';
import { AiProcessing } from '@/components/ai/AiProcessing';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelFoot, PanelHead, PanelTitle } from '@/components/ui/panel';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

type ConversationSummary = {
    id: string;
    title: string;
    last_message_at: string | null;
    message_count: number;
};

type Citation = {
    id: string;
    document_id: string;
    document_slug: string;
    document_title: string;
    page_number: number | null;
    section_heading: string | null;
    section_number: string | null;
    quote: string | null;
    url: string;
};

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    citations?: Citation[];
    insufficient_evidence?: boolean;
};

type Props = {
    conversations: ConversationSummary[];
    can: {
        search: boolean;
        compare: boolean;
        consistency: boolean;
    };
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function AiIndex({ conversations: initialConversations, can }: Props) {
    const { t } = useTranslations();
    const [conversations, setConversations] = useState(initialConversations);
    const [activeConversationId, setActiveConversationId] = useState<string | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [question, setQuestion] = useState('');
    const [loading, setLoading] = useState(false);
    const [asking, setAsking] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const threadRef = useRef<HTMLDivElement>(null);
    const busy = loading || asking;

    useEffect(() => {
        const thread = threadRef.current;

        if (!thread) {
            return;
        }

        thread.scrollTo({ top: thread.scrollHeight, behavior: 'smooth' });
    }, [asking, messages]);

    const loadConversation = useCallback(
        async (conversationId: string) => {
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(`/ai/conversations/${conversationId}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error(t('ai.load_error'));
                }

                const payload = await response.json();
                setActiveConversationId(conversationId);
                setMessages(payload.conversation.messages ?? []);
            } catch (loadError) {
                setError(loadError instanceof Error ? loadError.message : t('ai.load_error'));
            } finally {
                setLoading(false);
            }
        },
        [t],
    );

    async function handleAsk(event: FormEvent) {
        event.preventDefault();

        const trimmed = question.trim();

        if (trimmed.length < 3 || busy) {
            return;
        }

        setAsking(true);
        setError(null);

        const userMessage: ChatMessage = {
            id: `local-${Date.now()}`,
            role: 'user',
            content: trimmed,
        };

        setMessages((current) => [...current, userMessage]);
        setQuestion('');

        try {
            const response = await fetch('/ai/ask', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    question: trimmed,
                    conversation_id: activeConversationId,
                }),
            });

            if (!response.ok) {
                throw new Error(t('ai.ask_error'));
            }

            const payload = await response.json();
            setActiveConversationId(payload.conversation_id);

            const assistantMessage: ChatMessage = {
                id: payload.message.id,
                role: 'assistant',
                content: payload.message.content,
                citations: payload.message.citations ?? [],
                insufficient_evidence: payload.message.insufficient_evidence,
            };

            setMessages((current) => [...current, assistantMessage]);

            setConversations((current) => {
                const existing = current.find((item) => item.id === payload.conversation_id);

                if (existing) {
                    return current.map((item) =>
                        item.id === payload.conversation_id
                            ? {
                                  ...item,
                                  title: item.title || trimmed.slice(0, 80),
                                  message_count: item.message_count + 2,
                                  last_message_at: new Date().toISOString(),
                              }
                            : item,
                    );
                }

                return [
                    {
                        id: payload.conversation_id,
                        title: trimmed.slice(0, 80),
                        message_count: 2,
                        last_message_at: new Date().toISOString(),
                    },
                    ...current,
                ];
            });
        } catch (askError) {
            setError(askError instanceof Error ? askError.message : t('ai.ask_error'));
            setMessages((current) => current.filter((message) => message.id !== userMessage.id));
            setQuestion(trimmed);
        } finally {
            setAsking(false);
        }
    }

    function startNewConversation() {
        setActiveConversationId(null);
        setMessages([]);
        setError(null);
    }

    return (
        <AppLayout title={t('ai.title')}>
            <div className="mx-auto max-w-6xl space-y-6">
                <PageHeader title={t('ai.title')} description={t('ai.tools_subtitle')} />

                <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
                    <Panel as="aside" className="space-y-0">
                        <PanelBody className="space-y-3">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('ai.tools_title')}</h2>
                                <div className="mt-3 flex flex-col gap-2">
                                    {can.compare ? (
                                        <Button variant="secondary" size="sm" asChild>
                                            <Link href="/ai/compare">{t('ai.compare_link')}</Link>
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        </PanelBody>

                        <PanelHead sunk className="border-t">
                            <PanelTitle>{t('ai.conversations')}</PanelTitle>
                            <Button type="button" variant="secondary" size="sm" onClick={startNewConversation}>
                                {t('ai.new_conversation')}
                            </Button>
                        </PanelHead>

                        <PanelBody>
                            <ul className="space-y-1 text-sm">
                                {conversations.length === 0 ? (
                                    <li className="text-ink-muted">{t('ai.no_conversations')}</li>
                                ) : (
                                    conversations.map((conversation) => (
                                        <li key={conversation.id}>
                                            <button
                                                type="button"
                                                onClick={() => loadConversation(conversation.id)}
                                                className={cn(
                                                    'w-full rounded-[var(--radius-sm)] px-3 py-2 text-left transition-colors hover:bg-canvas-sunk',
                                                    activeConversationId === conversation.id && 'bg-canvas-sunk',
                                                )}
                                            >
                                                <p className="line-clamp-2 font-medium text-ink">{conversation.title}</p>
                                                <p className="text-xs text-ink-muted">
                                                    {t('ai.message_count', { count: conversation.message_count })}
                                                </p>
                                            </button>
                                        </li>
                                    ))
                                )}
                            </ul>
                        </PanelBody>
                    </Panel>

                    <Panel as="section" className="flex min-h-128 flex-col">
                        <PanelHead>
                            <PanelTitle>{t('ai.ask_title')}</PanelTitle>
                            <p className="text-sm text-ink-muted">{t('ai.ask_subtitle')}</p>
                        </PanelHead>

                        <PanelBody className="flex min-h-0 flex-1 flex-col overflow-hidden">
                            <div ref={threadRef} className="flex-1 space-y-4 overflow-y-auto">
                                {messages.length === 0 && !asking ? (
                                    <EmptyState bare title={t('ai.empty_state')} />
                                ) : (
                                    messages.map((message) => (
                                        <div
                                            key={message.id}
                                            className={cn(
                                                'animate-rise-in',
                                                message.role === 'user' && 'ml-8 rounded-[var(--radius-md)] bg-canvas-sunk px-4 py-3',
                                            )}
                                        >
                                            {message.role === 'assistant' ? (
                                                <AiContent>
                                                    <p className="whitespace-pre-wrap">{message.content}</p>
                                                    {message.citations && message.citations.length > 0 ? (
                                                        <div className="mt-4 space-y-2 border-t border-line pt-3">
                                                            <p className="label-eyebrow">{t('ai.citations')}</p>
                                                            <ul className="space-y-2">
                                                                {message.citations.map((citation) => (
                                                                    <li key={citation.id} className="text-sm">
                                                                        <a
                                                                            href={citation.url}
                                                                            className="font-medium text-accent hover:underline"
                                                                        >
                                                                            {citation.document_title}
                                                                        </a>
                                                                        {citation.page_number ? (
                                                                            <span className="text-ink-muted">
                                                                                {' '}
                                                                                · {t('ai.page', { number: citation.page_number })}
                                                                            </span>
                                                                        ) : null}
                                                                        {citation.section_number ? (
                                                                            <span className="text-ink-muted">
                                                                                {' '}
                                                                                ·{' '}
                                                                                {t('ai.section', { number: citation.section_number })}
                                                                            </span>
                                                                        ) : null}
                                                                        {citation.quote ? (
                                                                            <p className="mt-1 text-xs text-ink-muted">
                                                                                {citation.quote}
                                                                            </p>
                                                                        ) : null}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    ) : null}
                                                </AiContent>
                                            ) : (
                                                <p className="text-sm whitespace-pre-wrap text-ink">{message.content}</p>
                                            )}
                                        </div>
                                    ))
                                )}

                                {asking ? <AiProcessing /> : null}

                                {error ? <p className="text-sm text-critical">{error}</p> : null}
                            </div>
                        </PanelBody>

                        <PanelFoot>
                            <form onSubmit={handleAsk} className="flex w-full gap-2">
                                <Input
                                    value={question}
                                    onChange={(event) => setQuestion(event.target.value)}
                                    placeholder={t('ai.question_placeholder')}
                                    disabled={busy}
                                    aria-label={t('ai.question_placeholder')}
                                />
                                <Button type="submit" variant="primary" disabled={busy || question.trim().length < 3}>
                                    {asking ? t('ai.asking') : t('ai.ask')}
                                </Button>
                            </form>
                        </PanelFoot>
                    </Panel>
                </div>
            </div>
        </AppLayout>
    );
}
