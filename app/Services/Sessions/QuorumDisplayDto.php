<?php

namespace App\Services\Sessions;

readonly class QuorumDisplayDto
{
    public function __construct(
        public int $seatedCount,
        public int $presentCount,
        public int $required,
        public bool $met,
    ) {}

    /**
     * @return array{seated_count: int, present_count: int, required: int, met: bool}
     */
    public function toArray(): array
    {
        return [
            'seated_count' => $this->seatedCount,
            'present_count' => $this->presentCount,
            'required' => $this->required,
            'met' => $this->met,
        ];
    }
}
