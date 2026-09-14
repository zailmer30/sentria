<?php

namespace App\States\Minutes;

class AiDraft extends MinutesStatus
{
    public static string $name = 'ai-draft';

    public function label(): string
    {
        return 'AI Draft';
    }
}
