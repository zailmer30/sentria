<?php

namespace App\States\Session;

class MinutesForReview extends SessionStatus
{
    public static string $name = 'minutes-for-review';

    public function label(): string
    {
        return 'Minutes for Review';
    }
}
