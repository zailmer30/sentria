<?php

namespace App\States\Publication;

class SecretariatReview extends PublicationWorkflowStatus
{
    public static string $name = 'secretariat-review';

    public function label(): string
    {
        return 'Secretariat Review';
    }
}
