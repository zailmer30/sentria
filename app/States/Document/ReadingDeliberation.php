<?php

namespace App\States\Document;

class ReadingDeliberation extends DocumentWorkflowStatus
{
    public static string $name = 'reading-deliberation';

    public function label(): string
    {
        return 'Reading/Deliberation';
    }
}
