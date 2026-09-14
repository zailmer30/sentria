<?php

namespace App\States\Session;

class InSession extends SessionStatus
{
    public static string $name = 'in-session';

    public function label(): string
    {
        return 'In Session';
    }
}
