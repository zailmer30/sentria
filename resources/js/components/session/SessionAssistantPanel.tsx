import { AiContent } from '@/components/ai/AiContent';
import { SessionFloorFab } from '@/components/session/SessionFloorTools';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronDown, Search, Sparkles, X } from 'lucide-react';
import { FormEvent, useCallback, useId, useState, type ReactNode } from 'react';

export type AssistantRelatedSuggestion = {
    document_id: string;
    slug: string;
    title: string;
    reference_number: string | null;
    document_type_label: string;
    similarity: number;
    relevance_band: string;
    excerpt: string | null;
    is_ai_suggestion: boolean;
};

export type AssistantSearchHit = {
    embedding_id: string;
    document_id: string;
    document_slug: string;
    document_title: string;
    reference_number: string | null;
    chunk_text: string;
    similarity: number;
    page_number: number | null;
    section_heading: string | null;
    section_number: string | null;
    url: string;
};

export type AssistantVersionMeta = {
    id: string;
    version_number: number;
    is_current: boolean;
    original_filename: string;
    change_summary: string | null;
    uploaded_by: string | null;
    created_at: string | null;
};

export type AssistantSummary = {
    executive_summary: string;
    purpose?: string;
    key_provisions?: string[];
    model?: string;
};

export type SessionAssistantData = {
    available: boolean;
    current_agenda_item: {
        id: string;
        item_number: string | null;
        title: string;
        description: string | null;
        status: string;
        document?: { id: string; slug: string; title: string } | null;
    } | null;
    document: { id: string; slug: string; title: string } | null;
    summary: AssistantSummary | null;
    document_restricted: boolean;
    related_legislation: AssistantRelatedSuggestion[];
    previous_similar: AssistantSearchHit[];
    document_history: AssistantVersionMeta[];
};

type SessionAssistantPanelProps = {
    sessionId: string;
    assistant: SessionAssistantData | null;
    canUseAssistant: boolean;
    /** Extra classes for the floating toggle (e.g. lift above a sticky vote bar). */
    toggleClassName?: string;
    /** Hide the floating toggle when the host renders its own launcher. */
    hideToggle?: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

type ExpandableSectionProps = {
    title: string;
    defaultOpen?: boolean;
    children: ReactNode;
};

function ExpandableSection({ title, defaultOpen = false, children }: ExpandableSectionProps) {
    const [open, setOpen] = useState(defaultOpen);
    const panelId = useId();

    return (
        <section className="rounded-[var(--radius-md)] border border-line bg-surface">
            <button
                type="button"
                className="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-3 text-left"
                aria-expanded={open}
                aria-controls={panelId}
                onClick={() => setOpen((value) => !value)}
            >
                <span className="text-sm font-semibold text-ink">{title}</span>
                <ChevronDown
                    className={cn(
                        'size-4 shrink-0 text-ink-muted transition-transform duration-[var(--duration-base)]',
                        open && 'rotate-180',
                    )}
                    aria-hidden
                />
            </button>
            <div
                id={panelId}
                className={cn(
                    'session-assistant-expand grid overflow-hidden border-t border-line transition-[grid-template-rows] duration-[var(--duration-base)]',
                    open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                )}
            >
                <div className="min-h-0 overflow-hidden">
                    <div className="space-y-3 px-4 py-3">{children}</div>
                </div>
            </div>
        </section>
    );
}

function ListCard({ children }: { children: ReactNode }) {
    return <div className="rounded-[var(--radius-md)] border border-line bg-surface-alt px-3 py-2 text-sm">{children}</div>;
}

export function SessionAssistantPanel({
    sessionId,
    assistant,
    canUseAssistant,
    toggleClassName,
    hideToggle = false,
    open: openProp,
    onOpenChange,
}: SessionAssistantPanelProps) {
    const { t } = useTranslations();
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const open = openProp ?? uncontrolledOpen;

    function setOpen(next: boolean) {
        onOpenChange?.(next);
        if (openProp === undefined) {
            setUncontrolledOpen(next);
        }
    }
    const [query, setQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [searchHits, setSearchHits] = useState<AssistantSearchHit[]>([]);
    const csrf =
        typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') : null;

    const runSearch = useCallback(
        async (event: FormEvent) => {
            event.preventDefault();
            const trimmed = query.trim();

            if (trimmed.length < 2) {
                return;
            }

            setSearching(true);
            setSearchError(null);

            try {
                const response = await fetch(`/sessions/${sessionId}/assistant/search`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ query: trimmed }),
                });

                if (!response.ok) {
                    throw new Error('search_failed');
                }

                const payload = (await response.json()) as { hits: AssistantSearchHit[] };
                setSearchHits(payload.hits ?? []);
            } catch {
                setSearchError(t('sessions.assistant.search_error'));
                setSearchHits([]);
            } finally {
                setSearching(false);
            }
        },
        [csrf, query, sessionId, t],
    );

    if (!canUseAssistant) {
        return null;
    }

    return (
        <>
            {!hideToggle && !open ? (
                <SessionFloorFab className={cn('session-assistant-toggle', toggleClassName)}>
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon-floor"
                        className="size-14 rounded-full border-line bg-surface text-accent shadow-[var(--shadow-lg)] [&_svg]:size-5"
                        onClick={() => setOpen(true)}
                        aria-haspopup="dialog"
                        aria-expanded={open}
                        aria-label={t('sessions.assistant.open')}
                        title={t('sessions.assistant.open')}
                    >
                        <Sparkles aria-hidden strokeWidth={1.75} />
                    </Button>
                </SessionFloorFab>
            ) : null}

            {open ? (
                <div className="fixed inset-0 z-50 flex justify-end" role="presentation">
                    <button
                        type="button"
                        className="absolute inset-0 bg-ink/30"
                        aria-label={t('sessions.assistant.close')}
                        onClick={() => setOpen(false)}
                    />

                    <aside
                        role="dialog"
                        aria-modal="true"
                        aria-label={t('sessions.assistant.title')}
                        className="session-assistant-panel relative flex h-full w-full max-w-md flex-col border-l border-line bg-canvas shadow-[var(--shadow-lg)] sm:rounded-l-[var(--radius-xl)]"
                    >
                        <header className="flex items-start justify-between gap-3 border-b border-line bg-surface px-4 py-4">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-lg font-semibold text-ink">{t('sessions.assistant.title')}</h2>
                                    <span className="ai-badge">{t('sessions.assistant.badge')}</span>
                                </div>
                                <p className="mt-1 text-sm text-ink-muted">{t('sessions.assistant.subtitle')}</p>
                            </div>
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon-floor"
                                className="shrink-0"
                                onClick={() => setOpen(false)}
                                aria-label={t('sessions.assistant.close')}
                            >
                                <X className="size-5" aria-hidden />
                            </Button>
                        </header>

                        <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                            {!assistant?.available ? (
                                <p className="text-sm text-ink-muted">{t('sessions.assistant.unavailable')}</p>
                            ) : (
                                <>
                                    <ExpandableSection title={t('sessions.assistant.current_item')} defaultOpen>
                                        {assistant.current_agenda_item ? (
                                            <div className="space-y-2 text-sm">
                                                {assistant.current_agenda_item.item_number ? (
                                                    <p className="font-mono text-2xs text-ink-subtle">
                                                        {assistant.current_agenda_item.item_number}
                                                    </p>
                                                ) : null}
                                                <p className="font-medium text-ink">{assistant.current_agenda_item.title}</p>
                                                {assistant.current_agenda_item.description ? (
                                                    <p className="text-ink-muted">{assistant.current_agenda_item.description}</p>
                                                ) : null}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-ink-muted">{t('sessions.no_current_item')}</p>
                                        )}
                                    </ExpandableSection>

                                    {assistant.document_restricted ? (
                                        <AiContent>
                                            <p>{t('sessions.assistant.document_restricted')}</p>
                                        </AiContent>
                                    ) : null}

                                    {assistant.summary && !assistant.document_restricted ? (
                                        <ExpandableSection title={t('sessions.assistant.document_summary')} defaultOpen>
                                            <AiContent showBadge={false}>
                                                <p>{assistant.summary.executive_summary}</p>
                                                {assistant.summary.purpose ? (
                                                    <p className="mt-3 text-ink-muted">{assistant.summary.purpose}</p>
                                                ) : null}
                                                {assistant.summary.key_provisions &&
                                                assistant.summary.key_provisions.length > 0 ? (
                                                    <ul className="mt-3 list-disc space-y-1 pl-5">
                                                        {assistant.summary.key_provisions.map((item) => (
                                                            <li key={item}>{item}</li>
                                                        ))}
                                                    </ul>
                                                ) : null}
                                            </AiContent>
                                        </ExpandableSection>
                                    ) : null}

                                    {assistant.related_legislation.length > 0 ? (
                                        <ExpandableSection title={t('sessions.assistant.related_legislation')}>
                                            <ul className="space-y-3">
                                                {assistant.related_legislation.map((item) => (
                                                    <li key={item.document_id}>
                                                        <ListCard>
                                                            <Link
                                                                href={`/documents/${item.slug}`}
                                                                className="font-medium text-accent hover:underline"
                                                            >
                                                                {item.title}
                                                            </Link>
                                                            {item.excerpt ? (
                                                                <p className="mt-1 text-ink-muted">{item.excerpt}</p>
                                                            ) : null}
                                                        </ListCard>
                                                    </li>
                                                ))}
                                            </ul>
                                        </ExpandableSection>
                                    ) : null}

                                    {assistant.previous_similar.length > 0 ? (
                                        <ExpandableSection title={t('sessions.assistant.previous_similar')}>
                                            <ul className="space-y-3">
                                                {assistant.previous_similar.map((hit) => (
                                                    <li key={hit.embedding_id}>
                                                        <ListCard>
                                                            <Link
                                                                href={hit.url}
                                                                className="font-medium text-accent hover:underline"
                                                            >
                                                                {hit.document_title}
                                                            </Link>
                                                            <p className="mt-1 text-ink-muted">{hit.chunk_text}</p>
                                                        </ListCard>
                                                    </li>
                                                ))}
                                            </ul>
                                        </ExpandableSection>
                                    ) : null}

                                    {assistant.document_history.length > 0 && !assistant.document_restricted ? (
                                        <ExpandableSection title={t('sessions.assistant.document_history')}>
                                            <ul className="space-y-2 text-sm">
                                                {assistant.document_history.map((version) => (
                                                    <li key={version.id}>
                                                        <ListCard>
                                                            <p className="font-medium text-ink">
                                                                {t('sessions.assistant.version_label', {
                                                                    number: String(version.version_number),
                                                                })}
                                                                {version.is_current
                                                                    ? ` · ${t('sessions.assistant.current_version')}`
                                                                    : ''}
                                                            </p>
                                                            {version.change_summary ? (
                                                                <p className="mt-1 text-ink-muted">{version.change_summary}</p>
                                                            ) : null}
                                                            {version.uploaded_by ? (
                                                                <p className="mt-1 font-mono text-2xs text-ink-subtle">
                                                                    {version.uploaded_by}
                                                                </p>
                                                            ) : null}
                                                        </ListCard>
                                                    </li>
                                                ))}
                                            </ul>
                                        </ExpandableSection>
                                    ) : null}

                                    <ExpandableSection title={t('sessions.assistant.search')} defaultOpen>
                                        <form onSubmit={runSearch} className="space-y-3">
                                            <label htmlFor="session-assistant-search" className="sr-only">
                                                {t('sessions.assistant.search_placeholder')}
                                            </label>
                                            <div className="flex gap-2">
                                                <Input
                                                    id="session-assistant-search"
                                                    type="search"
                                                    value={query}
                                                    onChange={(event) => setQuery(event.target.value)}
                                                    placeholder={t('sessions.assistant.search_placeholder')}
                                                    className="h-12 min-w-0 flex-1 text-base"
                                                />
                                                <Button
                                                    type="submit"
                                                    variant="primary"
                                                    size="icon-floor"
                                                    disabled={searching || query.trim().length < 2}
                                                >
                                                    <Search className="size-5" aria-hidden />
                                                    <span className="sr-only">{t('sessions.assistant.search_action')}</span>
                                                </Button>
                                            </div>
                                            {searchError ? <p className="text-sm text-critical">{searchError}</p> : null}
                                        </form>

                                        {searchHits.length > 0 ? (
                                            <AiContent className="mt-3">
                                                <ul className="space-y-3">
                                                    {searchHits.map((hit) => (
                                                        <li key={hit.embedding_id}>
                                                            <Link
                                                                href={hit.url}
                                                                className="font-medium text-accent hover:underline"
                                                            >
                                                                {hit.document_title}
                                                            </Link>
                                                            <p className="mt-1 text-ink-muted">{hit.chunk_text}</p>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </AiContent>
                                        ) : searching ? (
                                            <p className="text-sm text-ink-muted">{t('sessions.assistant.searching')}</p>
                                        ) : null}
                                    </ExpandableSection>
                                </>
                            )}
                        </div>
                    </aside>
                </div>
            ) : null}
        </>
    );
}
