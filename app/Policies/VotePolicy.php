<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vote;

class VotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('voting.viewAny');
    }

    public function view(User $user, Vote $vote): bool
    {
        return $user->can('voting.viewAny') && $user->can('view', $vote->session);
    }

    public function update(User $user, Vote $vote): bool
    {
        return false;
    }

    public function delete(User $user, Vote $vote): bool
    {
        return false;
    }
}
