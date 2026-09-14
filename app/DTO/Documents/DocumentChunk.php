<?php

namespace App\DTO\Documents;

final readonly class DocumentChunk
{
    public function __construct(
        public string $text,
        public int $index,
        public ?string $sectionHeading = null,
        public ?string $sectionNumber = null,
        public ?int $pageNumber = null,
        public ?int $charStart = null,
        public ?int $charEnd = null,
    ) {}
}
