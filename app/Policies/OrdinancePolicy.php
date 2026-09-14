<?php

namespace App\Policies;

use App\Models\Ordinance;
use App\Models\User;

class OrdinancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('legislation.viewAny');
    }

    public function view(User $user, Ordinance $ordinance): bool
    {
        return $user->can('legislation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('legislation.manage');
    }

    public function update(User $user, Ordinance $ordinance): bool
    {
        return $user->can('legislation.manage');
    }
}
