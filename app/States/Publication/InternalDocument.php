<?php

namespace App\States\Publication;

class InternalDocument extends PublicationWorkflowStatus
{
    public static string $name = 'internal-document';

    public function label(): string
    {
        return 'Internal Document';
    }
}
