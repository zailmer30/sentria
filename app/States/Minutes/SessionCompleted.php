<?php

namespace App\States\Minutes;

class SessionCompleted extends MinutesStatus
{
    public static string $name = 'session-completed';

    public function label(): string
    {
        return 'Session Completed';
    }
}
