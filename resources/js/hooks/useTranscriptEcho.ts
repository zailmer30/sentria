import { sortTranscriptSegments, subscribeToTranscript, type TranscriptSegment } from '@/lib/echo';
import { useCallback, useEffect, useMemo, useState } from 'react';

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
                next[existing] = segment;

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
                    setLiveSegments(nextSegments);
                }
            },
        });
    }, [enabled, mergeSegment, sessionId]);

    const segments = useMemo(() => {
        const merged = new Map<number, TranscriptSegment>();

        initialSegments.forEach((segment) => merged.set(segment.index, segment));
        liveSegments.forEach((segment) => merged.set(segment.index, segment));

        return sortTranscriptSegments([...merged.values()]);
    }, [initialSegments, liveSegments]);

    return { segments, status, error, setSegments: setLiveSegments };
}
