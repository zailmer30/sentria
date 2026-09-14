<?php

namespace App\States\Document;

class Registered extends DocumentWorkflowStatus
{
    public static string $name = 'registered';

    public function label(): string
    {
        return 'Registered';
    }
}
