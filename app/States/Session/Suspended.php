<?php

namespace App\States\Session;

class Suspended extends SessionStatus
{
    public static string $name = 'suspended';

    public function label(): string
    {
        return 'Suspended';
    }
}
