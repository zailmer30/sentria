<?php

namespace App\States\Minutes;

class Review extends MinutesStatus
{
    public static string $name = 'review';

    public function label(): string
    {
        return 'Review';
    }
}
