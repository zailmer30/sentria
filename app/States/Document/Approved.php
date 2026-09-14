<?php

namespace App\States\Document;

class Approved extends DocumentWorkflowStatus
{
    public static string $name = 'approved';

    public function label(): string
    {
        return 'Approved';
    }
}
