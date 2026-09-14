<?php

namespace App\States\Session;

class AgendaPrepared extends SessionStatus
{
    public static string $name = 'agenda-prepared';

    public function label(): string
    {
        return 'Agenda Prepared';
    }
}
