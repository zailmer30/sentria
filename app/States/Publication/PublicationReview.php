<?php

namespace App\States\Publication;

class PublicationReview extends PublicationWorkflowStatus
{
    public static string $name = 'publication-review';

    public function label(): string
    {
        return 'Publication Review';
    }
}
