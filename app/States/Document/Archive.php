<?php

namespace App\States\Document;

class Archive extends DocumentWorkflowStatus
{
    public static string $name = 'archive';

    public function label(): string
    {
        return 'Archive';
    }
}
