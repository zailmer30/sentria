<?php

namespace App\States\Document;

class PublicPublication extends DocumentWorkflowStatus
{
    public static string $name = 'public-publication';

    public function label(): string
    {
        return 'Public Publication';
    }
}
