<?php

namespace App\DTO\Legislation;

final readonly class LegislativeHistoryEvent
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $stage,
        public string $label,
        public ?string $occurredAt,
        public ?string $description,
        public ?array $meta = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'label' => $this->label,
            'occurred_at' => $this->occurredAt,
            'description' => $this->description,
            'meta' => $this->meta,
        ];
    }
}
