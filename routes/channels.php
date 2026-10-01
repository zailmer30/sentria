<?php

use App\Models\LegislativeSession;
use App\Models\SessionConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return (string) $user->getKey() === (string) $id;
});

Broadcast::channel('session.{sessionId}', function (User $user, string $sessionId): bool {
    $session = LegislativeSession::query()->find($sessionId);

    return $session !== null && $user->can('view', $session);
});

Broadcast::channel('session-transcript.{sessionId}', function (User $user, string $sessionId): bool {
    $session = LegislativeSession::query()->find($sessionId);

    // Stricter than the general session channel: transcript subscribers must
    // also hold transcripts.view. Unauthorized users cannot subscribe.
    return $session !== null
        && $user->can('view', $session)
        && $user->can('transcripts.view');
});

Broadcast::channel('session-chat.{conversationId}', function (User $user, string $conversationId): bool {
    $conversation = SessionConversation::query()->with('session')->find($conversationId);

    if ($conversation === null) {
        return false;
    }

    return $user->can('view', $conversation);
});
