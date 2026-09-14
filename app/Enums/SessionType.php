<?php

namespace App\Enums;

enum SessionType: string
{
    case Regular = 'regular';
    case Special = 'special';
    case CommitteeHearing = 'committee-hearing';
    case PublicHearing = 'public-hearing';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular Session',
            self::Special => 'Special Session',
            self::CommitteeHearing => 'Committee Hearing',
            self::PublicHearing => 'Public Hearing',
        };
    }

    /**
     * Series prefix for generated session numbers (`RS-2026-00002`).
     */
    public function tag(): string
    {
        return match ($this) {
            self::Regular => 'RS',
            self::Special => 'SS',
            self::CommitteeHearing => 'CH',
            self::PublicHearing => 'PH',
        };
    }
}
