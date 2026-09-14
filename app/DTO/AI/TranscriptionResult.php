<?php

namespace App\DTO\AI;

final readonly class TranscriptionResult
{
    /**
     * @param  list<array{index: int, start: float, end: float, speaker?: string|null, speaker_id?: string|null, text: string, confidence?: float|null, language?: string|null}>  $segments
     */
    public function __construct(
        public string $fullText,
        public array $segments,
        public ?float $averageConfidence = null,
        public ?int $durationSeconds = null,
        public ?string $language = null,
        public ?string $provider = null,
        public ?string $model = null,
    ) {}
}
