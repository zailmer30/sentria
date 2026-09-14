<?php

namespace App\States\Minutes;

class FinalMinutes extends MinutesStatus
{
    public static string $name = 'final-minutes';

    public function label(): string
    {
        return 'Final Minutes';
    }
}
