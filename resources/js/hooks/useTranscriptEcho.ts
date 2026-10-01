import { sortTranscriptSegments, subscribeToTranscript, type TranscriptSegment } from '@/lib/echo';
import { useCallback, useEffect, useMemo, useState } from 'react';

function preserveOriginals(previous: TranscriptSegment, incoming: TranscriptSegment): TranscriptSegment {
    if (incoming.original_text !== undefined) {
        return incoming;
    }

    return {
        ...incoming,
        original_text: previous.original_text,
        original_speaker: previous.original_speaker,
        original_speaker_id: previous.original_speaker_id,
        original_attributed: previous.original_attributed,
        is_edited: incoming.is_edited ?? previous.is_edited,
        edited_at: incoming.edited_at ?? previous.edited_at,
        edited_by: incoming.edited_by ?? previous.edited_by,
    };
}

export function useTranscriptEcho(
    sessionId: string,
    initialSegments: TranscriptSegment[] = [],
    enabled = true,
    initial: { status?: string | null; error?: string | null } = {},
) {
    const [liveSegments, setLiveSegments] = useState<TranscriptSegment[]>([]);
    const [status, setStatus] = useState<string | null>(initial.status ?? null);
    const [error, setError] = useState<string | null>(initial.error ?? null);

    const mergeSegment = useCallback((segment: TranscriptSegment) => {
        setLiveSegments((current) => {
            const existing = current.findIndex((item) => item.index === segment.index);

            if (existing >= 0) {
                const next = [...current];
                const previous = next[existing];
                next[existing] = previous ? preserveOriginals(previous, segment) : segment;

                return next;
            }

            return [...current, segment];
        });
    }, []);

    useEffect(() => {
        setStatus(initial.status ?? null);
        setError(initial.error ?? null);
    }, [initial.error, initial.status]);

    useEffect(() => {
        if (!enabled) {
            return undefined;
        }

        return subscribeToTranscript(sessionId, {
            onSegment: ({ segment }) => mergeSegment(segment),
            onUpdated: ({ status: nextStatus, error: nextError, segments: nextSegments }) => {
                if (nextStatus) {
                    setStatus(nextStatus);
                }

                if (nextError !== undefined) {
                    setError(nextError);
                } else if (nextStatus === 'completed') {
                    setError(null);
                }

                if (nextSegments && nextSegments.length > 0) {
                    setLiveSegments((current) => {
                        const known = new Map(current.map((segment) => [segment.index, segment]));

                        return nextSegments.map((segment) => {
                            const previous = known.get(segment.index);

                            return previous ? preserveOriginals(previous, segment) : segment;
                        });
                    });
                }
            },
        });
    }, [enabled, mergeSegment, sessionId]);

    const segments = useMemo(() => {
        const merged = new Map<number, TranscriptSegment>();

        initialSegments.forEach((segment) => merged.set(segment.index, segment));
        liveSegments.forEach((segment) => {
            const previous = merged.get(segment.index);
            merged.set(segment.index, previous ? preserveOriginals(previous, segment) : segment);
        });

        return sortTranscriptSegments([...merged.values()]);
    }, [initialSegments, liveSegments]);

    return { segments, status, error, setSegments: setLiveSegments };
}
