<?php

namespace App\DTO\Legislation;

use App\Enums\LegislationKind;

final readonly class LegislationCitationKey
{
    public function __construct(
        public LegislationKind $kind,
        public int $year,
        public int $sequence,
    ) {}

    public function value(): string
    {
        return sprintf('%s:%d:%d', $this->kind->value, $this->year, $this->sequence);
    }
}
