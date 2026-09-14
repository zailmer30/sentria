<?php

namespace App\States\Session;

class Archived extends SessionStatus
{
    public static string $name = 'archived';

    public function label(): string
    {
        return 'Archived';
    }
}
