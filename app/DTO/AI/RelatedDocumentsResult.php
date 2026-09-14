<?php

namespace App\DTO\AI;

final readonly class RelatedDocumentsResult
{
    /**
     * @param  list<RelatedDocumentSuggestion>  $suggestions
     */
    public function __construct(
        public array $suggestions,
        public bool $isAiSuggestion = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_ai_suggestion' => $this->isAiSuggestion,
            'suggestions' => array_map(
                static fn (RelatedDocumentSuggestion $suggestion): array => $suggestion->toArray(),
                $this->suggestions,
            ),
        ];
    }
}
