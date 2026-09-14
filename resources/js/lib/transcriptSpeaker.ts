import type { TranscriptSegment } from '@/lib/echo';

export function isSegmentAttributed(segment: TranscriptSegment): boolean {
    return segment.attributed !== false;
}

export function segmentSpeakerLabel(segment: TranscriptSegment, unattributed: string): string {
    if (!isSegmentAttributed(segment)) {
        return unattributed;
    }

    const name = segment.speaker?.trim();

    return name && name !== '' ? name : unattributed;
}
