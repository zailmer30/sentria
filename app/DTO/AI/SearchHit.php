<?php

namespace App\DTO\AI;

final readonly class SearchHit
{
    public function __construct(
        public string $embeddingId,
        public string $documentId,
        public string $documentVersionId,
        public string $chunkText,
        public float $similarity,
        public ?int $pageNumber = null,
        public ?string $sectionHeading = null,
        public ?string $sectionNumber = null,
    ) {}
}
