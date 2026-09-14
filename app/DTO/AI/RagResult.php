<?php

namespace App\DTO\AI;

final readonly class RagResult
{
    /**
     * @param  list<RagCitation>  $citations
     */
    public function __construct(
        public string $conversationId,
        public string $messageId,
        public string $content,
        public array $citations,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public bool $insufficientEvidence,
    ) {}
}
