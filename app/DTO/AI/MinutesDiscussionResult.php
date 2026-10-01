<?php

namespace App\DTO\AI;

final readonly class MinutesDiscussionResult
{
    /**
     * @param  array<string, string>  $paragraphs  Agenda item id → prefixed discussion line
     */
    public function __construct(
        public array $paragraphs,
        public bool $skipped,
        public ?string $skipReason,
        public ?string $chatModel,
    ) {}
}
