<?php

namespace App\DTO\AI;

final readonly class ContextChunk
{
    public function __construct(
        public int $index,
        public string $embeddingId,
        public string $documentId,
        public string $documentVersionId,
        public string $documentTitle,
        public string $documentSlug,
        public string $chunkText,
        public float $similarity,
        public ?int $pageNumber = null,
        public ?string $sectionHeading = null,
        public ?string $sectionNumber = null,
    ) {}
}
