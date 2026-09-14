<?php

namespace App\States\Document;

class CommitteeReferral extends DocumentWorkflowStatus
{
    public static string $name = 'committee-referral';

    public function label(): string
    {
        return 'Committee Referral';
    }
}
