<?php

namespace App\States\Document;

class Rejected extends DocumentWorkflowStatus
{
    public static string $name = 'rejected';

    public function label(): string
    {
        return 'Rejected';
    }
}
