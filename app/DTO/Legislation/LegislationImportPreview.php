<?php

namespace App\DTO\Legislation;

final readonly class LegislationImportPreview
{
    /**
     * @param  list<array<string, mixed>>  $matched
     * @param  list<array<string, mixed>>  $unmatchedRows
     * @param  list<array<string, mixed>>  $unmatchedFiles
     * @param  list<array<string, mixed>>  $duplicates
     * @param  list<array<string, mixed>>  $invalid
     */
    public function __construct(
        public array $matched,
        public array $unmatchedRows,
        public array $unmatchedFiles,
        public array $duplicates,
        public array $invalid,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'matched' => $this->matched,
            'unmatched_rows' => $this->unmatchedRows,
            'unmatched_files' => $this->unmatchedFiles,
            'duplicates' => $this->duplicates,
            'invalid' => $this->invalid,
            'counts' => [
                'matched' => count($this->matched),
                'unmatched_rows' => count($this->unmatchedRows),
                'unmatched_files' => count($this->unmatchedFiles),
                'duplicates' => count($this->duplicates),
                'invalid' => count($this->invalid),
            ],
        ];
    }
}
