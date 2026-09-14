<?php

namespace App\DTO\AI;

final readonly class ConsistencyFinding
{
    public function __construct(
        public string $type,
        public string $severity,
        public string $message,
        public ?string $locationHint = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,
            'location_hint' => $this->locationHint,
        ];
    }
}
