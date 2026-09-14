<?php

namespace App\States\Document;

class Voting extends DocumentWorkflowStatus
{
    public static string $name = 'voting';

    public function label(): string
    {
        return 'Voting';
    }
}
