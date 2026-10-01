<?php

namespace App\Policies;

use App\Models\LegislativeSession;
use App\Models\SessionConversation;
use App\Models\User;

class SessionConversationPolicy
{
    public function view(User $user, SessionConversation $conversation): bool
    {
        $session = $conversation->session;

        if (! $session instanceof LegislativeSession) {
            return false;
        }

        return $user->can('useChat', $session) && $conversation->isCurrentParticipant($user);
    }

    public function send(User $user, SessionConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function updateParticipants(User $user, SessionConversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && $conversation->isGroup()
            && $conversation->created_by === $user->getKey();
    }

    public function leave(User $user, SessionConversation $conversation): bool
    {
        return $this->view($user, $conversation) && $conversation->isGroup();
    }
}
