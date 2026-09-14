<?php

namespace App\Policies;

use App\Models\Motion;
use App\Models\User;

class MotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('motions.viewAny');
    }

    public function view(User $user, Motion $motion): bool
    {
        return $user->can('motions.viewAny') && $user->can('view', $motion->session);
    }

    public function create(User $user): bool
    {
        return $user->can('motions.create');
    }

    public function second(User $user, Motion $motion): bool
    {
        return $user->can('motions.second')
            && $motion->status === 'proposed'
            && $motion->moved_by !== $user->getKey();
    }

    public function withdraw(User $user, Motion $motion): bool
    {
        return $user->can('motions.withdraw') && $motion->moved_by === $user->getKey();
    }

    public function rule(User $user, Motion $motion): bool
    {
        return $user->can('motions.rule');
    }
}
