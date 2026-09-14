<?php

namespace App\Policies;

use App\Models\FloorRecognitionRequest;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Session\InSession;

class FloorRecognitionRequestPolicy
{
    public function create(User $user, LegislativeSession $session): bool
    {
        return $user->can('motions.create')
            && $user->can('view', $session)
            && $session->status instanceof InSession;
    }

    public function cancel(User $user, FloorRecognitionRequest $request): bool
    {
        return $request->isPending()
            && $request->user_id === $user->getKey();
    }

    public function recognize(User $user, FloorRecognitionRequest $request): bool
    {
        return $user->can('motions.rule')
            && $request->isPending()
            && $user->can('sessions.view');
    }

    public function dismiss(User $user, FloorRecognitionRequest $request): bool
    {
        return $user->can('motions.rule')
            && ($request->isPending() || $request->isOpenRecognized())
            && $user->can('sessions.view');
    }
}
