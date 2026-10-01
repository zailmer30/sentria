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

export function segmentIsEdited(segment: TranscriptSegment): boolean {
    if (typeof segment.original_text === 'string' && segment.text !== segment.original_text) {
        return true;
    }

    if (
        segment.original_speaker !== undefined ||
        segment.original_speaker_id !== undefined ||
        segment.original_attributed !== undefined
    ) {
        const originalAttributed = segment.original_attributed ?? true;
        const attributed = segment.attributed ?? true;

        if (attributed !== originalAttributed) {
            return true;
        }

        if ((segment.speaker_id ?? null) !== (segment.original_speaker_id ?? null)) {
            return true;
        }

        if ((segment.speaker ?? null) !== (segment.original_speaker ?? null)) {
            return true;
        }
    }

    return segment.is_edited === true;
}
