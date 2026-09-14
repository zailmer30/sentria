<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;

class PublicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('publications.viewAny');
    }

    public function view(User $user, Publication $publication): bool
    {
        return $user->can('publications.viewAny');
    }

    public function create(User $user): bool
    {
        return $user->can('publications.review');
    }

    public function transition(User $user, Publication $publication): bool
    {
        return $user->can('publications.review') || $user->can('publications.publish');
    }
}
