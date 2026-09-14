<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\PrivateNote;
use App\Models\User;
use App\Services\Documents\DocumentAccessService;

class PrivateNotePolicy
{
    public function __construct(private readonly DocumentAccessService $access) {}

    public function view(User $user, PrivateNote $note): bool
    {
        return $note->user_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PrivateNote $note): bool
    {
        return $note->user_id === $user->getKey();
    }

    public function delete(User $user, PrivateNote $note): bool
    {
        return $note->user_id === $user->getKey();
    }

    public function createForNotable(User $user, string $notableType, string $notableId): bool
    {
        if ($notableType === LegislativeSession::class) {
            $session = LegislativeSession::query()->find($notableId);

            return $session !== null && $user->can('view', $session);
        }

        if ($notableType === Document::class) {
            $document = Document::query()->find($notableId);

            return $document !== null && $this->access->userCanView($user, $document);
        }

        return false;
    }
}
