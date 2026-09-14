<?php

namespace App\States\Minutes;

class Approval extends MinutesStatus
{
    public static string $name = 'approval';

    public function label(): string
    {
        return 'Approval';
    }
}
