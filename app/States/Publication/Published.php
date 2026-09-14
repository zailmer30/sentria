<?php

namespace App\States\Publication;

class Published extends PublicationWorkflowStatus
{
    public static string $name = 'published';

    public function label(): string
    {
        return 'Published';
    }
}
