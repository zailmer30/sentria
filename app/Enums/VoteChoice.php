<?php

namespace App\Enums;

enum VoteChoice: string
{
    case Yes = 'yes';
    case No = 'no';
    case Abstain = 'abstain';
    /** Voluntary recusal, typically for conflict of interest. */
    case Inhibit = 'inhibit';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Yes',
            self::No => 'No',
            self::Abstain => 'Abstain',
            self::Inhibit => 'Inhibit',
        };
    }

    public function countsTowardTally(): bool
    {
        return in_array($this, [self::Yes, self::No], true);
    }
}
