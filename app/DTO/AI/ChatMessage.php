<?php

namespace App\DTO\AI;

final readonly class ChatMessage
{
    public function __construct(
        public string $role,
        public string $content,
    ) {}
}
