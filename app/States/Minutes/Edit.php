<?php

namespace App\States\Minutes;

class Edit extends MinutesStatus
{
    public static string $name = 'edit';

    public function label(): string
    {
        return 'Edit';
    }
}
