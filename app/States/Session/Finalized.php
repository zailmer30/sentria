<?php

namespace App\States\Session;

class Finalized extends SessionStatus
{
    public static string $name = 'finalized';

    public function label(): string
    {
        return 'Finalized';
    }
}
