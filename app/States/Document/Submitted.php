<?php

namespace App\States\Document;

class Submitted extends DocumentWorkflowStatus
{
    public static string $name = 'submitted';

    public function label(): string
    {
        return 'Document Submitted';
    }
}
