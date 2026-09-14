<?php

namespace App\States\Document;

class Transmittal extends DocumentWorkflowStatus
{
    public static string $name = 'transmittal';

    public function label(): string
    {
        return 'Transmittal';
    }
}
