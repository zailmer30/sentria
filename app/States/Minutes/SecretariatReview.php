<?php

namespace App\States\Minutes;

class SecretariatReview extends MinutesStatus
{
    public static string $name = 'secretariat-review';

    public function label(): string
    {
        return 'Secretariat Review';
    }
}
