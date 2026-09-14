<?php

namespace App\States\Session;

class Adjourned extends SessionStatus
{
    public static string $name = 'adjourned';

    public function label(): string
    {
        return 'Adjourned';
    }
}
