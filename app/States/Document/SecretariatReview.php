<?php

namespace App\States\Document;

class SecretariatReview extends DocumentWorkflowStatus
{
    public static string $name = 'secretariat-review';

    public function label(): string
    {
        return 'Secretariat Review';
    }
}
