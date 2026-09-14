<?php

namespace App\Contracts\AI;

use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\User;

interface MinutesGenerationService
{
    public function draftFromSession(User $actor, LegislativeSession $session): Minutes;
}
