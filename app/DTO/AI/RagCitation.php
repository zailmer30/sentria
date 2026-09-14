<?php

namespace App\DTO\AI;

final readonly class RagCitation
{
    public function __construct(
        public string $id,
        public string $documentId,
        public string $documentSlug,
        public string $documentTitle,
        public ?string $documentVersionId,
        public ?string $embeddingId,
        public int $rank,
        public ?float $similarity,
        public ?int $pageNumber,
        public ?string $sectionHeading,
        public ?string $sectionNumber,
        public ?string $quote,
    ) {}
}
