<?php

namespace App\Policies;

use App\Models\AgendaItem;
use App\Models\MinutesCorrection;
use App\Models\User;

class MinutesCorrectionPolicy
{
    public function create(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage') && $user->can('view', $item->session);
    }

    public function update(User $user, MinutesCorrection $correction): bool
    {
        return $user->can('agenda.manage') && $user->can('view', $correction->session);
    }

    public function delete(User $user, MinutesCorrection $correction): bool
    {
        return $user->can('agenda.manage') && $user->can('view', $correction->session);
    }

    public function apply(User $user, MinutesCorrection $correction): bool
    {
        return $user->can('agenda.manage') && $user->can('view', $correction->session);
    }
}
