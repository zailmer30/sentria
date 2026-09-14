<?php

namespace App\States\Minutes;

class Archive extends MinutesStatus
{
    public static string $name = 'archive';

    public function label(): string
    {
        return 'Archive';
    }
}
