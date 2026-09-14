<?php

namespace App\DTO\AI;

final readonly class SessionAssistantContext
{
    /**
     * @param  array<string, mixed>|null  $currentAgendaItem
     * @param  array<string, mixed>|null  $document
     * @param  array<string, mixed>|null  $summary
     * @param  list<array<string, mixed>>  $relatedLegislation
     * @param  list<array<string, mixed>>  $previousSimilar
     * @param  list<array<string, mixed>>  $documentHistory
     */
    public function __construct(
        public bool $available,
        public ?array $currentAgendaItem = null,
        public ?array $document = null,
        public ?array $summary = null,
        public bool $documentRestricted = false,
        public array $relatedLegislation = [],
        public array $previousSimilar = [],
        public array $documentHistory = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'current_agenda_item' => $this->currentAgendaItem,
            'document' => $this->document,
            'summary' => $this->summary,
            'document_restricted' => $this->documentRestricted,
            'related_legislation' => $this->relatedLegislation,
            'previous_similar' => $this->previousSimilar,
            'document_history' => $this->documentHistory,
        ];
    }
}
