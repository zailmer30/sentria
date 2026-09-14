<?php

namespace App\Policies;

use App\Models\User;

class ChamberChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.viewAny') || $user->can('settings.chamber') || $user->can('transcripts.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('settings.update') || $user->can('settings.chamber') || $user->can('transcripts.manage');
    }
}
