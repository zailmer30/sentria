<?php

namespace App\States\Session;

class Draft extends SessionStatus
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'Draft';
    }
}
