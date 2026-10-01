<?php

namespace App\Policies;

use App\Models\AgendaItem;
use App\Models\User;

class AgendaItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('agenda.viewAny');
    }

    public function view(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.viewAny') && $user->can('view', $item->session);
    }

    public function create(User $user): bool
    {
        return $user->can('agenda.manage');
    }

    public function update(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }

    public function delete(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }

    public function reorder(User $user): bool
    {
        return $user->can('agenda.manage');
    }

    public function advance(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }

    public function retreat(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }

    public function beginHeadingVotes(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }

    public function calendarSecondReading(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }

    public function calendarThirdReading(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }

    public function postpone(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }

    public function undoPostpone(User $user, AgendaItem $item): bool
    {
        return $user->can('agenda.manage');
    }
}
