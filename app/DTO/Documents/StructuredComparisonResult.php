<?php

namespace App\DTO\Documents;

final readonly class StructuredComparisonResult
{
    /**
     * @param  list<array<string, mixed>>  $changedSections
     * @param  list<array<string, mixed>>  $amountChanges
     * @param  list<array<string, mixed>>  $dateChanges
     * @param  list<array<string, mixed>>  $penaltyChanges
     * @param  list<array<string, mixed>>  $definitionChanges
     */
    public function __construct(
        public ComparisonResult $lineDiff,
        public array $changedSections,
        public array $amountChanges,
        public array $dateChanges,
        public array $penaltyChanges,
        public array $definitionChanges,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hunks' => $this->lineDiff->toArray()['hunks'],
            'changed_sections' => $this->changedSections,
            'amount_changes' => $this->amountChanges,
            'date_changes' => $this->dateChanges,
            'penalty_changes' => $this->penaltyChanges,
            'definition_changes' => $this->definitionChanges,
        ];
    }
}
