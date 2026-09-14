<?php

namespace App\States\Document;

class CommitteeReview extends DocumentWorkflowStatus
{
    public static string $name = 'committee-review';

    public function label(): string
    {
        return 'Committee Review';
    }
}
