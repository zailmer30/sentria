<?php

namespace App\States\Document;

class AgendaInclusion extends DocumentWorkflowStatus
{
    public static string $name = 'agenda-inclusion';

    public function label(): string
    {
        return 'Agenda Inclusion';
    }
}
