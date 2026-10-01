<?php

namespace App\Services\Sessions;

readonly class QuorumDisplayDto
{
    /**
     * @param  list<array{id: string, display_name: string|null, avatar_url: string|null}>  $presentMembers
     */
    public function __construct(
        public int $seatedCount,
        public int $presentCount,
        public int $required,
        public bool $met,
        public string $rule = 'majority_of_seated',
        public array $presentMembers = [],
    ) {}

    /**
     * @return array{seated_count: int, present_count: int, required: int, met: bool, rule: string, present_members: list<array{id: string, display_name: string|null, avatar_url: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'seated_count' => $this->seatedCount,
            'present_count' => $this->presentCount,
            'required' => $this->required,
            'met' => $this->met,
            'rule' => $this->rule,
            'present_members' => $this->presentMembers,
        ];
    }
}
