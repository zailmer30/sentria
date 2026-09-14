<?php

namespace App\DTO\Legislation;

final readonly class LegislationImportCsvRow
{
    /**
     * @param  array<string, string|null>  $fields
     */
    public function __construct(
        public int $line,
        public string $number,
        public int $year,
        public string $title,
        public string $status,
        public array $fields,
        public ?LegislationCitationKey $key,
        public ?string $error = null,
    ) {}

    public function isValid(): bool
    {
        return $this->key !== null && $this->error === null;
    }
}
