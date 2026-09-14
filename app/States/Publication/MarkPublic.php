<?php

namespace App\States\Publication;

class MarkPublic extends PublicationWorkflowStatus
{
    public static string $name = 'mark-public';

    public function label(): string
    {
        return 'Mark Public';
    }
}
