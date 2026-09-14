import { AiContent } from '@/components/ai/AiContent';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { FileDrop } from '@/components/ui/file-drop';
import { Input, Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { Progress } from '@/components/ui/progress';
import { SimpleSelect } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/users/UserAvatar';
import { useTranscriptEcho } from '@/hooks/useTranscriptEcho';
import SessionLayout from '@/layouts/SessionLayout';
import type { TranscriptSegment } from '@/lib/echo';
import { EMPTY_VALUE, useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { isLowConfidence, useLowConfidenceThreshold } from '@/lib/transcriptConfidence';
import { isSegmentAttributed, segmentSpeakerLabel } from '@/lib/transcriptSpeaker';
import { cn } from '@/lib/utils';
import { Link, router, useForm } from '@inertiajs/react';
import { AudioLines, CloudUpload, Download, Maximize2, MessageSquare, Minimize2, Pencil, Search, Type } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

type SegmentKind = 'motion' | 'debate' | 'ruling' | 'roll_call' | 'remark';

type AgendaItemOption = {
    id: string;
    item_number: string | null;
    title: string;
};

type TranscriptData = {
    id: string;
    status: string;
    processing_error: string | null;
    source: string;
    language: string;
    full_text: string | null;
    segments: TranscriptSegment[];
    average_confidence: number | null;
    duration_seconds: number | null;
    model: string | null;
    provider: string | null;
    started_at: string | null;
    ended_at: string | null;
    agenda_item: AgendaItemOption | null;
};

type SearchResult = {
    index: number;
    start: number;
    end: number;
    speaker: string | null;
    text: string;
    agenda_title: string | null;
    jump_url: string;
};

type Props = {
    session: {
        id: string;
        session_number: string;
        title: string;
        status: string;
        status_label: string;
        recording_enabled?: boolean;
    };
    transcript: TranscriptData | null;
    agenda_items: AgendaItemOption[];
    roster?: { id: string; display_name: string | null }[];
    can: {
        manage: boolean;
        transcribe: boolean;
        correct: boolean;
        view: boolean;
    };
    highlight_seconds: number;
};

const KIND_ORDER: SegmentKind[] = ['motion', 'debate', 'ruling', 'roll_call', 'remark'];

const KIND_BADGE: Record<SegmentKind, string> = {
    motion: 'border-[var(--color-info-line)] bg-info-soft text-info',
    debate: 'border-[var(--color-info-line)] bg-info-soft text-info',
    ruling: 'border-[var(--color-success-line)] bg-success-soft text-success',
    roll_call: 'border-[var(--color-warning-line)] bg-warning-soft text-warning',
    remark: 'border-line bg-canvas-sunk text-ink-muted',
};

const AVATAR_TONES = [
    'bg-accent-soft text-accent-ink',
    'bg-info-soft text-info',
    'bg-success-soft text-success',
    'bg-warning-soft text-warning',
    'bg-[var(--color-vote-inhibit-soft)] text-[var(--color-vote-inhibit)]',
];

const TEXT_SIZES = ['text-sm', 'text-[0.9375rem]', 'text-base'] as const;

const AUDIO_ACCEPT = 'audio/wav,audio/x-wav,audio/wave,audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/m4a,.wav,.mp3,.m4a';
const AUDIO_EXTENSIONS = ['.wav', '.mp3', '.m4a'];
const AUDIO_MAX_BYTES = 50 * 1024 * 1024;

function formatAgendaNumber(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const parts = value.split('.');

    if (parts.length > 0 && parts.every((part) => /^\d+$/.test(part))) {
        const head = parts[0] ?? '';

        return [head.padStart(2, '0'), ...parts.slice(1)].join('.');
    }

    const digits = value.replace(/\D/g, '');

    return digits.length > 0 ? digits.padStart(2, '0') : value;
}

function formatTimestamp(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);

    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function formatCapturedDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined || seconds < 0) {
        return EMPTY_VALUE;
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours <= 0) {
        return `${minutes} m`;
    }

    return `${hours} h ${minutes} m`;
}

function engineLabel(model: string | null | undefined): string {
    if (!model) {
        return 'Whisper';
    }

    if (/^whisper-?1$/i.test(model)) {
        return 'Whisper';
    }

    return model
        .replace(/^whisper-?/i, 'Whisper ')
        .replace(/\s+/g, ' ')
        .trim();
}

function classifySegment(text: string): SegmentKind {
    const value = text.toLowerCase();

    if (/\b(roll[\s-]?call|all those in (favor|favour)|aye|nay|voting is now open|cast your vote)\b/.test(value)) {
        return 'roll_call';
    }

    if (/\b(the chair rules|ruling|out of order|sustained|overruled|point of order)\b/.test(value)) {
        return 'ruling';
    }

    if (/\b(i move|moved that|motion to|seconded|the motion|without objection)\b/.test(value)) {
        return 'motion';
    }

    if (/\b(yield|in debate|speak (in favor|against)|rebuttal|for the record)\b/.test(value)) {
        return 'debate';
    }

    return 'remark';
}

function speakerTone(name: string): string {
    let hash = 0;

    for (let index = 0; index < name.length; index++) {
        hash = (hash + name.charCodeAt(index) * (index + 1)) % AVATAR_TONES.length;
    }

    return AVATAR_TONES[hash] ?? AVATAR_TONES[0] ?? 'bg-accent-soft text-accent-ink';
}

function segmentClock(
    startedAt: string | null,
    offsetSeconds: number,
    formatTime: (value: string | null | undefined) => string,
): string {
    if (!startedAt) {
        return formatTimestamp(offsetSeconds);
    }

    const start = new Date(startedAt);

    if (Number.isNaN(start.getTime())) {
        return formatTimestamp(offsetSeconds);
    }

    return formatTime(new Date(start.getTime() + offsetSeconds * 1000).toISOString());
}

export default function SessionTranscript({
    session,
    transcript,
    agenda_items,
    roster = [],
    can,
    highlight_seconds,
}: Props) {
    const { t } = useTranslations();
    const { formatTime } = useFormatters();
    const initialSegments = transcript?.segments ?? [];
    const { segments, status: liveStatus, error: liveError } = useTranscriptEcho(
        session.id,
        initialSegments,
        can.view,
        {
            status: transcript?.status,
            error: transcript?.processing_error,
        },
    );
    const transcriptStatus = liveStatus ?? transcript?.status ?? null;
    const transcriptError = liveError ?? transcript?.processing_error ?? null;
    const [query, setQuery] = useState('');
    const [speakerFilter, setSpeakerFilter] = useState('');
    const [kindFilter, setKindFilter] = useState<SegmentKind | 'all'>('all');
    const [editingIndex, setEditingIndex] = useState<number | null>(null);
    const [editText, setEditText] = useState('');
    const [editSpeaker, setEditSpeaker] = useState('');
    const [saving, setSaving] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const [textSize, setTextSize] = useState(0);
    const [editMode, setEditMode] = useState(false);
    const [followLive, setFollowLive] = useState(true);
    const [hideLowConfidence, setHideLowConfidence] = useState(false);
    const lowConfidenceThreshold = useLowConfidenceThreshold();
    const segmentRefs = useRef<Map<number, HTMLElement>>(new Map());
    const feedListRef = useRef<HTMLUListElement | null>(null);

    const uploadForm = useForm<{ audio: File | null; agenda_item_id: string }>({
        audio: null,
        agenda_item_id: '',
    });

    const itemLabel = transcript?.agenda_item
        ? t('transcripts.item', { number: formatAgendaNumber(transcript.agenda_item.item_number) })
        : null;

    const speakers = useMemo(() => {
        const names = new Set<string>();

        segments.forEach((segment) => {
            if (segment.speaker?.trim()) {
                names.add(segment.speaker.trim());
            }
        });

        return [...names].sort((a, b) => a.localeCompare(b));
    }, [segments]);

    const annotated = useMemo(
        () =>
            segments.map((segment) => ({
                ...segment,
                kind: classifySegment(segment.text),
                speakerName: segmentSpeakerLabel(segment, t('transcripts.unattributed')),
                lowConfidence: isLowConfidence(segment.confidence, lowConfidenceThreshold),
            })),
        [lowConfidenceThreshold, segments, t],
    );

    const lowConfidenceCount = useMemo(
        () => annotated.filter((segment) => segment.lowConfidence).length,
        [annotated],
    );

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return annotated.filter((segment) => {
            if (kindFilter !== 'all' && segment.kind !== kindFilter) {
                return false;
            }

            if (speakerFilter && segment.speakerName !== speakerFilter) {
                return false;
            }

            if (hideLowConfidence && segment.lowConfidence) {
                return false;
            }

            if (!needle) {
                return true;
            }

            return segment.text.toLowerCase().includes(needle) || segment.speakerName.toLowerCase().includes(needle);
        });
    }, [annotated, hideLowConfidence, kindFilter, query, speakerFilter]);

    const feed = useMemo(() => [...visible].reverse(), [visible]);

    const floorShares = useMemo(() => {
        const counts = new Map<string, number>();

        annotated.forEach((segment) => {
            counts.set(segment.speakerName, (counts.get(segment.speakerName) ?? 0) + 1);
        });

        const total = annotated.length || 1;

        return [...counts.entries()]
            .map(([speaker, count]) => ({
                speaker,
                count,
                percent: Math.round((count / total) * 100),
            }))
            .sort((a, b) => b.count - a.count);
    }, [annotated]);

    const scrollToSegment = useCallback(
        (seconds: number) => {
            const match = segments.find(
                (segment) => Math.floor(segment.start) === seconds || (segment.start <= seconds && segment.end >= seconds),
            );

            if (match) {
                segmentRefs.current.get(match.index)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },
        [segments],
    );

    useEffect(() => {
        if (highlight_seconds > 0) {
            scrollToSegment(highlight_seconds);
        }
    }, [highlight_seconds, scrollToSegment]);

    useEffect(() => {
        if (!followLive || feed.length === 0 || highlight_seconds > 0) {
            return;
        }

        feedListRef.current?.scrollTo({ top: 0 });
    }, [feed.length, followLive, highlight_seconds, segments.length]);

    async function runSearch(event: FormEvent) {
        event.preventDefault();

        if (!query.trim()) {
            return;
        }

        try {
            const response = await fetch(`/sessions/${session.id}/transcript/search?query=${encodeURIComponent(query.trim())}`, {
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                const data = (await response.json()) as { results: SearchResult[] };
                const first = data.results[0];

                if (first) {
                    scrollToSegment(Math.floor(first.start));
                }
            }
        } catch {
            /* Search still filters the feed locally. */
        }
    }

    function openEditor(segment: TranscriptSegment) {
        setEditingIndex(segment.index);
        setEditText(segment.text);
        setEditSpeaker(
            !isSegmentAttributed(segment)
                ? ''
                : segment.speaker_id
                  ? segment.speaker_id
                  : '__gallery__',
        );
        setEditMode(true);
    }

    async function saveCorrection() {
        if (editingIndex === null || !transcript) {
            return;
        }

        setSaving(true);

        const body: { text: string; speaker_id?: string; gallery?: boolean } = { text: editText };

        if (editSpeaker === '__gallery__') {
            body.gallery = true;
        } else if (editSpeaker) {
            body.speaker_id = editSpeaker;
        }

        try {
            const response = await fetch(`/sessions/${session.id}/transcript/${transcript.id}/segments/${editingIndex}`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(body),
            });

            if (response.ok) {
                setEditingIndex(null);
                router.reload({ only: ['transcript'] });
            }
        } finally {
            setSaving(false);
        }
    }

    function takeAudio(next: File | null) {
        if (!next) {
            uploadForm.setData((data) => ({ ...data, audio: null }));
            uploadForm.clearErrors('audio');

            return;
        }

        const extension = `.${next.name.split('.').pop()?.toLowerCase() ?? ''}`;

        if (extension !== '.' && !AUDIO_EXTENSIONS.includes(extension)) {
            uploadForm.setData((data) => ({ ...data, audio: next }));
            uploadForm.setError('audio', t('transcripts.audio_type_invalid'));

            return;
        }

        if (next.size > AUDIO_MAX_BYTES) {
            uploadForm.setData((data) => ({ ...data, audio: next }));
            uploadForm.setError('audio', t('transcripts.audio_too_large'));

            return;
        }

        uploadForm.clearErrors('audio');
        uploadForm.setData((data) => ({ ...data, audio: next }));
    }

    function handleUpload(event: FormEvent) {
        event.preventDefault();

        if (!uploadForm.data.audio) {
            uploadForm.setError('audio', t('transcripts.audio_required'));

            return;
        }

        if (uploadForm.errors.audio) {
            return;
        }

        uploadForm.post(`/sessions/${session.id}/transcript`, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    function exportTranscript() {
        if (annotated.length === 0) {
            return;
        }

        const body = [
            `${t('transcripts.title')} — ${session.session_number}`,
            session.title,
            '',
            ...annotated.map((segment) => {
                const when = segmentClock(transcript?.started_at ?? null, segment.start, formatTime);
                const kind = t(`transcripts.kind_${segment.kind}`);

                return `[${when}] ${segment.speakerName} · ${kind}${segment.lowConfidence ? ` · ${t('transcripts.unverified_export')}` : ''}\n${segment.text}`;
            }),
        ].join('\n\n');

        const blob = new Blob([body], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${session.session_number}-transcript.txt`;
        link.click();
        URL.revokeObjectURL(url);
    }

    function jumpToLatest() {
        setFollowLive((current) => {
            const next = !current;

            if (next) {
                requestAnimationFrame(() => {
                    feedListRef.current?.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }

            return next;
        });
    }

    return (
        <SessionLayout
            title={t('transcripts.title')}
            heading={t('transcripts.title')}
            description={`${session.session_number} · ${t('transcripts.subtitle')}`}
            sessionId={session.id}
            sessionStatus={session.status}
            liveLabel={t('transcripts.recording')}
            headerActions={
                <Button type="button" variant="primary" disabled={annotated.length === 0} onClick={exportTranscript}>
                    <Download aria-hidden="true" strokeWidth={1.75} className="size-4" />
                    {t('transcripts.export')}
                </Button>
            }
        >
            <div className="space-y-4">
                <AiContent compact>{t('transcripts.ai_notice')}</AiContent>

                {transcriptStatus === 'failed' || transcriptError ? (
                    <Notice tone="danger">
                        <p className="font-medium">{t('transcripts.failed')}</p>
                        <p className="mt-1">{transcriptError || t('transcripts.failed_unknown')}</p>
                    </Notice>
                ) : session.status !== 'in-session' || session.recording_enabled === false ? (
                    <Notice tone="caution">
                        <p className="font-medium">{t('transcripts.live_idle_title')}</p>
                        <p className="mt-1">
                            {t('transcripts.live_idle_body')}{' '}
                            <Link href={`/sessions/${session.id}`} className="font-medium underline underline-offset-2">
                                {t('transcripts.open_session')}
                            </Link>
                        </p>
                    </Notice>
                ) : null}

                <div className={cn('grid items-start gap-4', expanded ? 'grid-cols-1' : 'xl:grid-cols-[minmax(0,1fr)_22rem]')}>
                    <Panel as="section" aria-live="polite" className="relative">
                        <PanelHead className="gap-y-3">
                            <div className="flex min-w-0 items-baseline gap-2">
                                <PanelTitle>{t('transcripts.live_feed')}</PanelTitle>
                                {transcriptStatus === 'failed' ? (
                                    <span className="text-xs font-medium text-critical">{t('transcripts.failed')}</span>
                                ) : transcriptStatus === 'processing' || transcriptStatus === 'pending' ? (
                                    <span className="text-xs text-ink-faint">{t('transcripts.processing')}</span>
                                ) : null}
                                <span className="text-xs text-ink-faint">
                                    {t('transcripts.segments_count', {
                                        shown: visible.length,
                                        total: annotated.length,
                                    })}
                                </span>
                            </div>
                        </PanelHead>

                        <div className="space-y-3 border-b border-line px-5 py-3">
                            <form onSubmit={runSearch} className="flex flex-col gap-2 sm:flex-row">
                                <label className="min-w-0 flex-1">
                                    <span className="sr-only">{t('transcripts.search')}</span>
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-faint"
                                            aria-hidden
                                        />
                                        <Input
                                            type="search"
                                            value={query}
                                            onChange={(event) => setQuery(event.target.value)}
                                            placeholder={t('transcripts.search_placeholder')}
                                            className="pl-9"
                                        />
                                    </div>
                                </label>
                                <SimpleSelect
                                    value={speakerFilter}
                                    onValueChange={setSpeakerFilter}
                                    noneLabel={t('transcripts.all_speakers')}
                                    placeholder={t('transcripts.all_speakers')}
                                    aria-label={t('transcripts.all_speakers')}
                                    className="sm:w-44"
                                    items={speakers.map((speaker) => ({ value: speaker, label: speaker }))}
                                />
                            </form>

                            <div className="flex flex-wrap gap-1.5" role="group" aria-label={t('transcripts.kind_filters')}>
                                <KindChip
                                    active={kindFilter === 'all'}
                                    onClick={() => setKindFilter('all')}
                                    label={t('transcripts.kind_all')}
                                />
                                {KIND_ORDER.map((kind) => (
                                    <KindChip
                                        key={kind}
                                        active={kindFilter === kind}
                                        onClick={() => setKindFilter(kind)}
                                        label={t(`transcripts.kind_${kind}`)}
                                    />
                                ))}
                                {lowConfidenceCount > 0 ? (
                                    <KindChip
                                        active={hideLowConfidence}
                                        onClick={() => setHideLowConfidence((current) => !current)}
                                        label={
                                            hideLowConfidence
                                                ? t('transcripts.show_low_confidence')
                                                : t('transcripts.hide_low_confidence')
                                        }
                                    />
                                ) : null}
                            </div>
                            {hideLowConfidence && lowConfidenceCount > 0 ? (
                                <p className="text-xs text-warning">
                                    {t('transcripts.low_confidence_hidden', { count: lowConfidenceCount })}
                                </p>
                            ) : null}
                        </div>

                        {visible.length === 0 ? (
                            <PanelBody>
                                <EmptyState
                                    bare
                                    title={annotated.length === 0 ? t('transcripts.empty') : t('transcripts.no_matching')}
                                />
                            </PanelBody>
                        ) : (
                            <ul
                                ref={feedListRef}
                                className="max-h-[min(40rem,calc(100dvh-18rem))] divide-y divide-line overflow-y-auto pb-16"
                            >
                                {feed.map((segment) => {
                                    const highlighted =
                                        highlight_seconds > 0 &&
                                        segment.start <= highlight_seconds &&
                                        segment.end >= highlight_seconds;
                                    const clock = segmentClock(transcript?.started_at ?? null, segment.start, formatTime);
                                    const confidence =
                                        typeof segment.confidence === 'number' ? Math.round(segment.confidence * 100) : null;

                                    return (
                                        <li
                                            key={segment.index}
                                            ref={(node) => {
                                                if (node) {
                                                    segmentRefs.current.set(segment.index, node);
                                                }
                                            }}
                                            className={cn(
                                                'group px-5 py-4',
                                                highlighted && 'bg-accent-soft',
                                                segment.lowConfidence &&
                                                    'border-l-2 border-l-warning bg-warning-soft/40',
                                            )}
                                            aria-label={segment.lowConfidence ? t('transcripts.low_confidence') : undefined}
                                        >
                                            <div className="flex items-start gap-3">
                                                <UserAvatar
                                                    name={segment.speakerName}
                                                    className="mt-0.5 size-8"
                                                    fallbackClassName={speakerTone(segment.speakerName)}
                                                />

                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                        <p className="text-sm font-semibold text-ink">{segment.speakerName}</p>
                                                        <time
                                                            dateTime={`PT${Math.floor(segment.start)}S`}
                                                            className="font-mono text-2xs text-ink-faint"
                                                        >
                                                            {clock}
                                                        </time>
                                                        <Badge className={KIND_BADGE[segment.kind]}>
                                                            {t(`transcripts.kind_${segment.kind}`)}
                                                        </Badge>
                                                        {segment.lowConfidence ? (
                                                            <Badge className="border-warning-line bg-warning-soft text-warning">
                                                                {t('transcripts.low_confidence')}
                                                            </Badge>
                                                        ) : null}
                                                        {itemLabel ? (
                                                            <span className="text-2xs font-medium text-ink-faint">
                                                                {itemLabel}
                                                            </span>
                                                        ) : null}
                                                    </div>

                                                    <p
                                                        className={cn(
                                                            'mt-1.5 leading-6',
                                                            TEXT_SIZES[textSize],
                                                            segment.lowConfidence ? 'text-ink-muted' : 'text-ink',
                                                        )}
                                                    >
                                                        {segment.text}
                                                    </p>

                                                    {segment.lowConfidence ? (
                                                        <p className="mt-2 text-xs text-warning">
                                                            {t('transcripts.low_confidence_hint')}
                                                        </p>
                                                    ) : null}

                                                    {confidence !== null ? (
                                                        <p
                                                            className={cn(
                                                                'mt-2 font-mono text-2xs tracking-[0.12em] uppercase',
                                                                segment.lowConfidence ? 'text-warning' : 'text-ink-faint',
                                                            )}
                                                        >
                                                            {t('transcripts.confidence')} {confidence}%
                                                        </p>
                                                    ) : null}
                                                </div>

                                                {can.correct ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label={t('transcripts.correct_segment')}
                                                        className={cn(
                                                            !editMode &&
                                                                'opacity-0 group-hover:opacity-100 focus-visible:opacity-100',
                                                        )}
                                                        onClick={() => openEditor(segment)}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        <FeedToolbar
                            expanded={expanded}
                            editMode={editMode}
                            followLive={followLive}
                            canCorrect={can.correct}
                            onToggleExpanded={() => setExpanded((current) => !current)}
                            onCycleTextSize={() => setTextSize((current) => (current + 1) % TEXT_SIZES.length)}
                            onToggleEditMode={() => can.correct && setEditMode((current) => !current)}
                            onFollowLive={jumpToLatest}
                            t={t}
                        />
                    </Panel>

                    {expanded ? null : (
                        <aside className="flex flex-col gap-4 xl:sticky xl:top-32 xl:self-start">
                            {can.transcribe ? (
                                <Panel as="section" className="overflow-hidden">
                                    <div className="bg-floor-plate px-5 py-4">
                                        <p className="text-eyebrow text-floor-ink-muted">{t('transcripts.speech_to_text')}</p>
                                        <h2 className="mt-1 text-sm font-semibold text-floor-ink">{t('transcripts.upload')}</h2>
                                    </div>
                                    <PanelBody className="space-y-4">
                                        <form onSubmit={handleUpload} className="space-y-4">
                                            <FileDrop
                                                id="transcript-audio"
                                                file={uploadForm.data.audio}
                                                onFileChange={takeAudio}
                                                accept={AUDIO_ACCEPT}
                                                disabled={uploadForm.processing}
                                                invalid={Boolean(uploadForm.errors.audio)}
                                                icon={CloudUpload}
                                                dropLabel={t('transcripts.upload_drop')}
                                                browseLabel={t('transcripts.upload_browse')}
                                                replaceLabel={t('transcripts.audio_replace')}
                                                removeLabel={t('transcripts.audio_remove')}
                                                emptyHint={t('transcripts.upload_hint')}
                                                className="border-dashed border-line-strong bg-canvas-sunk/40"
                                            />
                                            {uploadForm.errors.audio ? (
                                                <p className="text-xs font-medium text-critical">{uploadForm.errors.audio}</p>
                                            ) : null}

                                            <label className="block text-sm">
                                                <span className="mb-1.5 block text-xs text-ink-subtle">
                                                    {t('transcripts.agenda_item')}
                                                </span>
                                                <SimpleSelect
                                                    value={uploadForm.data.agenda_item_id}
                                                    onValueChange={(value) => uploadForm.setData('agenda_item_id', value)}
                                                    noneLabel={t('transcripts.no_agenda')}
                                                    items={agenda_items.map((item) => ({
                                                        value: item.id,
                                                        label: `${item.item_number} — ${item.title}`,
                                                    }))}
                                                />
                                            </label>

                                            <Button
                                                type="submit"
                                                disabled={
                                                    uploadForm.processing
                                                    || !uploadForm.data.audio
                                                    || Boolean(uploadForm.errors.audio)
                                                }
                                                className="h-10 w-full border-floor-plate bg-floor-plate text-floor-ink hover:bg-floor-plate hover:brightness-110"
                                            >
                                                <AudioLines aria-hidden="true" strokeWidth={1.75} className="size-4" />
                                                {uploadForm.processing
                                                    ? t('transcripts.uploading')
                                                    : t('transcripts.start_transcription')}
                                            </Button>
                                        </form>
                                        <p className="text-xs text-ink-faint">{t('transcripts.upload_async')}</p>
                                    </PanelBody>
                                </Panel>
                            ) : null}

                            <Panel as="section">
                                <PanelHead>
                                    <div>
                                        <PanelTitle>{t('transcripts.floor_time')}</PanelTitle>
                                        <p className="mt-0.5 text-xs text-ink-faint">{t('transcripts.floor_time_help')}</p>
                                    </div>
                                </PanelHead>
                                <PanelBody className="space-y-3.5">
                                    {floorShares.length === 0 ? (
                                        <p className="text-sm text-ink-subtle">{t('transcripts.empty')}</p>
                                    ) : (
                                        floorShares.map((row) => (
                                            <div key={row.speaker} className="flex items-center gap-3">
                                                <UserAvatar
                                                    name={row.speaker}
                                                    className="size-7"
                                                    fallbackClassName={speakerTone(row.speaker)}
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-ink">{row.speaker}</p>
                                                    <Progress value={row.percent} className="mt-1.5" />
                                                </div>
                                                <p className="shrink-0 font-mono text-2xs text-ink-muted">
                                                    {row.count} · {row.percent}%
                                                </p>
                                            </div>
                                        ))
                                    )}
                                </PanelBody>
                            </Panel>

                            <Panel as="section">
                                <PanelHead>
                                    <PanelTitle>{t('transcripts.session_details')}</PanelTitle>
                                </PanelHead>
                                <PanelBody className="py-1">
                                    <dl className="divide-y divide-line">
                                        <DetailRow
                                            label={t('transcripts.recording_started')}
                                            value={formatTime(transcript?.started_at)}
                                        />
                                        <DetailRow
                                            label={t('transcripts.duration_captured')}
                                            value={formatCapturedDuration(transcript?.duration_seconds)}
                                        />
                                        <DetailRow label={t('transcripts.segments')} value={String(annotated.length)} />
                                        <DetailRow
                                            label={t('transcripts.engine')}
                                            value={annotated.length > 0 ? engineLabel(transcript?.model) : EMPTY_VALUE}
                                        />
                                    </dl>
                                </PanelBody>
                            </Panel>
                        </aside>
                    )}
                </div>
            </div>

            <Dialog open={editingIndex !== null} onOpenChange={(open) => !open && setEditingIndex(null)}>
                <DialogContent title={t('transcripts.correct_segment')}>
                    {can.correct ? (
                        <label className="block text-sm">
                            <span className="mb-1.5 block text-xs text-ink-subtle">{t('transcripts.assign_speaker')}</span>
                            <SimpleSelect
                                value={editSpeaker}
                                onValueChange={setEditSpeaker}
                                noneLabel={t('transcripts.pick_speaker')}
                                items={[
                                    { value: '__gallery__', label: t('transcripts.gallery') },
                                    ...roster
                                        .filter((row) => row.id)
                                        .map((row) => ({
                                            value: row.id,
                                            label: row.display_name ?? row.id,
                                        })),
                                ]}
                            />
                        </label>
                    ) : null}
                    <Textarea value={editText} onChange={(event) => setEditText(event.target.value)} rows={5} />
                    <DialogFooter>
                        <Button type="button" variant="secondary" onClick={() => setEditingIndex(null)}>
                            {t('transcripts.cancel')}
                        </Button>
                        <Button type="button" variant="primary" onClick={saveCorrection} disabled={saving}>
                            {saving ? t('transcripts.saving') : t('transcripts.save_correction')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SessionLayout>
    );
}

function KindChip({ active, onClick, label }: { active: boolean; onClick: () => void; label: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'rounded-full border px-2.5 py-1 text-2xs font-medium transition-colors',
                active
                    ? 'border-ink bg-ink text-ink-inverse'
                    : 'border-line bg-surface text-ink-muted hover:bg-canvas-sunk hover:text-ink',
            )}
        >
            {label}
        </button>
    );
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-3 py-2.5">
            <dt className="text-xs text-ink-subtle">{label}</dt>
            <dd className="font-mono text-xs text-ink">{value}</dd>
        </div>
    );
}

function FeedToolbar({
    expanded,
    editMode,
    followLive,
    canCorrect,
    onToggleExpanded,
    onCycleTextSize,
    onToggleEditMode,
    onFollowLive,
    t,
}: {
    expanded: boolean;
    editMode: boolean;
    followLive: boolean;
    canCorrect: boolean;
    onToggleExpanded: () => void;
    onCycleTextSize: () => void;
    onToggleEditMode: () => void;
    onFollowLive: () => void;
    t: (key: string) => string;
}) {
    return (
        <TooltipProvider>
            <div className="pointer-events-none absolute inset-x-0 bottom-4 z-10 flex justify-center">
                <div
                    role="toolbar"
                    aria-label={t('transcripts.feed_tools')}
                    className="pointer-events-auto flex items-center gap-0.5 rounded-full border border-line bg-surface px-1.5 py-1 shadow-md"
                >
                    <ToolButton
                        label={expanded ? t('transcripts.collapse_feed') : t('transcripts.expand_feed')}
                        pressed={expanded}
                        onClick={onToggleExpanded}
                    >
                        {expanded ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
                    </ToolButton>
                    <ToolButton label={t('transcripts.text_size')} onClick={onCycleTextSize}>
                        <Type className="size-4" />
                    </ToolButton>
                    <ToolButton
                        label={t('transcripts.edit_mode')}
                        pressed={editMode}
                        disabled={!canCorrect}
                        onClick={onToggleEditMode}
                    >
                        <Pencil className="size-4" />
                    </ToolButton>
                    <ToolButton label={t('transcripts.follow_live')} pressed={followLive} onClick={onFollowLive}>
                        <MessageSquare className="size-4" />
                    </ToolButton>
                </div>
            </div>
        </TooltipProvider>
    );
}

function ToolButton({
    label,
    pressed,
    disabled,
    onClick,
    children,
}: {
    label: string;
    pressed?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label={label}
                    aria-pressed={pressed}
                    disabled={disabled}
                    onClick={onClick}
                    className={cn('rounded-full', pressed && 'bg-canvas-sunk text-ink')}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
