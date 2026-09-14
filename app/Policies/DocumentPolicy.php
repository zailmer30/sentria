<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentAccessService;

class DocumentPolicy
{
    public function __construct(private readonly DocumentAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('documents.viewAny');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->access->userCanView($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.create');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->can('documents.update') && $this->access->userCanView($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('documents.delete') && $this->access->userCanView($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->access->userCanDownload($user, $document);
    }

    public function uploadVersion(User $user, Document $document): bool
    {
        return $this->access->userCanUploadVersion($user, $document);
    }

    public function grantAccess(User $user, Document $document): bool
    {
        return $user->can('documents.grantAccess') && $this->access->userCanView($user, $document);
    }

    public function archive(User $user, Document $document): bool
    {
        return $user->can('documents.archive') && $this->access->userCanView($user, $document);
    }

    public function transition(User $user, Document $document): bool
    {
        return $this->access->userCanView($user, $document);
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->can('documents.delete') && $this->access->userCanView($user, $document);
    }
}
