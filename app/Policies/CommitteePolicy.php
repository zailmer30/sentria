<?php

namespace App\Policies;

use App\Models\Committee;
use App\Models\User;

class CommitteePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('committees.viewAny');
    }

    public function view(User $user, Committee $committee): bool
    {
        return $user->can('committees.view');
    }

    public function create(User $user): bool
    {
        return $user->can('committees.manage');
    }

    public function manage(User $user): bool
    {
        return $user->can('committees.manage');
    }

    public function manageMembers(User $user, Committee $committee): bool
    {
        return $user->can('committees.manageMembers');
    }
}
