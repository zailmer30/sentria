import { AiContent } from '@/components/ai/AiContent';
import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { StatusChip } from '@/components/ui/status';
import type { TranscriptSegment } from '@/lib/echo';
import { useTranslations } from '@/lib/i18n';
import { isLowConfidence, useLowConfidenceThreshold } from '@/lib/transcriptConfidence';
import { segmentSpeakerLabel } from '@/lib/transcriptSpeaker';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Mic } from 'lucide-react';

type LiveTranscriptStripProps = {
    sessionId: string;
    transcript: {
        id: string;
        status: string;
        processing_error?: string | null;
        segments: TranscriptSegment[];
    } | null;
    canView: boolean;
};

function formatTimestamp(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);

    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

export function LiveTranscriptStrip({ sessionId, transcript, canView }: LiveTranscriptStripProps) {
    const { t } = useTranslations();
    const threshold = useLowConfidenceThreshold();

    if (!canView) {
        return null;
    }

    const latest = transcript?.segments?.at(-1);
    const latestUnverified = latest ? isLowConfidence(latest.confidence, threshold) : false;

    return (
        <section
            aria-label={t('transcripts.live_strip')}
            className="rounded-[var(--radius-md)] border border-line bg-surface px-4 py-3 shadow-[var(--shadow-xs)]"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Mic aria-hidden="true" strokeWidth={1.75} className="size-4 text-ink-faint" />
                    <h3 className="text-sm font-semibold text-ink">{t('transcripts.live')}</h3>
                    {transcript?.status === 'failed' || transcript?.processing_error ? (
                        <StatusChip tone="blocked" size="sm">
                            {t('transcripts.failed')}
                        </StatusChip>
                    ) : transcript?.status === 'processing' ? (
                        <StatusChip tone="review" size="sm">
                            {t('transcripts.processing')}
                        </StatusChip>
                    ) : null}
                </div>
                <Button variant="secondary" size="sm" asChild>
                    <Link href={`/sessions/${sessionId}/transcript`}>{t('transcripts.open_full')}</Link>
                </Button>
            </div>

            {transcript?.processing_error ? (
                <Notice tone="danger" compact className="mt-2.5">
                    {transcript.processing_error}
                </Notice>
            ) : null}

            {latest ? (
                <AiContent className="mt-2.5" showBadge={false}>
                    <p className="font-mono text-2xs text-ink-subtle">
                        {`${segmentSpeakerLabel(latest, t('transcripts.unattributed'))} · `}
                        {formatTimestamp(latest.start)}
                    </p>
                    <p
                        className={cn(
                            'mt-1 line-clamp-2 text-sm',
                            latestUnverified && 'text-ink-muted',
                        )}
                    >
                        {latest.text}
                    </p>
                    {latestUnverified ? (
                        <p className="mt-1 text-2xs text-warning">{t('transcripts.low_confidence_hint')}</p>
                    ) : null}
                </AiContent>
            ) : transcript?.processing_error ? null : (
                <p className="mt-2.5 text-sm text-ink-subtle">{t('transcripts.empty_live')}</p>
            )}
        </section>
    );
}
