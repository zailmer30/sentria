<?php

namespace App\Policies;

use App\Models\Resolution;
use App\Models\User;

class ResolutionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('legislation.viewAny');
    }

    public function view(User $user, Resolution $resolution): bool
    {
        return $user->can('legislation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('legislation.manage');
    }

    public function update(User $user, Resolution $resolution): bool
    {
        return $user->can('legislation.manage');
    }
}
