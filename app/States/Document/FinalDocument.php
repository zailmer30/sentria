<?php

namespace App\States\Document;

class FinalDocument extends DocumentWorkflowStatus
{
    public static string $name = 'final-document';

    public function label(): string
    {
        return 'Final Document';
    }
}
