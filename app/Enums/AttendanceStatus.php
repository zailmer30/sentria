<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';
    case OnOfficialBusiness = 'on-official-business';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::Excused => 'Excused',
            self::OnOfficialBusiness => 'On Official Business',
        };
    }

    /**
     * Whether the member is counted present for quorum purposes. The system
     * reports the count; the presiding officer decides whether to proceed.
     */
    public function countsTowardQuorum(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }
}
