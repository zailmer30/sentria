<?php

namespace App\Enums;

enum LegislationKind: string
{
    case Ordinance = 'ordinance';
    case Resolution = 'resolution';

    public function documentType(): DocumentType
    {
        return match ($this) {
            self::Ordinance => DocumentType::Ordinance,
            self::Resolution => DocumentType::Resolution,
        };
    }

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        return match ($this) {
            self::Ordinance => ['draft', 'pending', 'enacted', 'vetoed', 'repealed'],
            self::Resolution => ['draft', 'pending', 'adopted', 'withdrawn'],
        };
    }

    public function numberColumn(): string
    {
        return match ($this) {
            self::Ordinance => 'ordinance_number',
            self::Resolution => 'resolution_number',
        };
    }

    public function defaultStatus(): string
    {
        return match ($this) {
            self::Ordinance => 'enacted',
            self::Resolution => 'adopted',
        };
    }
}
