<?php

namespace App\Policies;

use App\Models\Minutes;
use App\Models\User;
use App\States\Minutes\Archive;
use App\States\Minutes\FinalMinutes;

class MinutesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('minutes.viewAny');
    }

    public function view(User $user, Minutes $minutes): bool
    {
        return $user->can('minutes.view') && $user->can('view', $minutes->session);
    }

    public function create(User $user): bool
    {
        return $user->can('minutes.edit');
    }

    public function update(User $user, Minutes $minutes): bool
    {
        return $user->can('minutes.edit')
            && ! ($minutes->status instanceof FinalMinutes)
            && ! ($minutes->status instanceof Archive);
    }

    public function generateDraft(User $user, Minutes $minutes): bool
    {
        return $user->can('minutes.generateDraft') && $user->can('view', $minutes->session);
    }

    public function review(User $user, Minutes $minutes): bool
    {
        return $user->can('minutes.review');
    }

    public function approve(User $user, Minutes $minutes): bool
    {
        return $user->can('minutes.approve');
    }

    public function finalize(User $user, Minutes $minutes): bool
    {
        return $user->can('minutes.finalize');
    }

    public function archive(User $user, Minutes $minutes): bool
    {
        return $user->can('minutes.finalize') || $user->can('sessions.archive');
    }
}
