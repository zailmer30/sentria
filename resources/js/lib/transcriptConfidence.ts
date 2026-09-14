import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

export const DEFAULT_LOW_CONFIDENCE = 0.4;

export function isLowConfidence(
    confidence: number | null | undefined,
    threshold: number = DEFAULT_LOW_CONFIDENCE,
): boolean {
    return typeof confidence === 'number' && Number.isFinite(confidence) && confidence < threshold;
}

export function useLowConfidenceThreshold(): number {
    const value = usePage<PageProps>().props.ai?.transcription_low_confidence;

    return typeof value === 'number' && Number.isFinite(value) && value > 0 ? value : DEFAULT_LOW_CONFIDENCE;
}
