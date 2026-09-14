<?php

namespace App\States\Document;

class CommitteeReport extends DocumentWorkflowStatus
{
    public static string $name = 'committee-report';

    public function label(): string
    {
        return 'Committee Report';
    }
}
