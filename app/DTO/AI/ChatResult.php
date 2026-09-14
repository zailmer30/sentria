<?php

namespace App\DTO\AI;

final readonly class ChatResult
{
    public function __construct(
        public string $content,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
