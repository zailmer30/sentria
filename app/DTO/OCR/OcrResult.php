<?php

namespace App\DTO\OCR;

final readonly class OcrResult
{
    public function __construct(
        public string $text,
        public ?int $pages = null,
        public string $engine = 'null',
    ) {}
}
