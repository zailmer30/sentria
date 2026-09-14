<?php

namespace App\DTO\AI;

final readonly class RelatedDocumentSuggestion
{
    public function __construct(
        public string $documentId,
        public string $slug,
        public string $title,
        public ?string $referenceNumber,
        public string $documentTypeLabel,
        public float $similarity,
        public string $relevanceBand,
        public ?string $excerpt,
        public bool $isAiSuggestion = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'slug' => $this->slug,
            'title' => $this->title,
            'reference_number' => $this->referenceNumber,
            'document_type_label' => $this->documentTypeLabel,
            'similarity' => round($this->similarity, 4),
            'relevance_band' => $this->relevanceBand,
            'excerpt' => $this->excerpt,
            'is_ai_suggestion' => $this->isAiSuggestion,
        ];
    }
}
