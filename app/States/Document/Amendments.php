<?php

namespace App\States\Document;

class Amendments extends DocumentWorkflowStatus
{
    public static string $name = 'amendments';

    public function label(): string
    {
        return 'Amendments';
    }
}
