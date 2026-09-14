<?php

namespace App\States\Document;

class ReturnedForRevision extends DocumentWorkflowStatus
{
    public static string $name = 'returned-for-revision';

    public function label(): string
    {
        return 'Returned for Revision';
    }
}
