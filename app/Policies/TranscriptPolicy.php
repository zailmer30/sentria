<?php

namespace App\Policies;

use App\Models\Transcript;
use App\Models\User;

class TranscriptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transcripts.viewAny');
    }

    public function view(User $user, Transcript $transcript): bool
    {
        return $user->can('transcripts.view') && $user->can('view', $transcript->session);
    }

    public function correct(User $user, Transcript $transcript): bool
    {
        return $user->can('transcripts.manage') && $user->can('view', $transcript->session);
    }
}
