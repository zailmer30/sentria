<?php

namespace App\States\Session;

class Scheduled extends SessionStatus
{
    public static string $name = 'scheduled';

    public function label(): string
    {
        return 'Scheduled';
    }
}
