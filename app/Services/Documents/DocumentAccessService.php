<?php

namespace App\Services\Documents;

use App\Enums\Confidentiality;
use App\Enums\ProcessingStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\DocumentVersion;
use App\Models\User;
use App\States\Document\ReturnedForRevision;
use Illuminate\Database\Eloquent\Builder;

class DocumentAccessService
{
    /** @var list<string> */
    private const ELEVATED_ROLES = [
        UserRole::SystemAdministrator->value,
        UserRole::Secretariat->value,
        UserRole::LegalTechnicalReviewer->value,
    ];

    public function userCanView(User $user, Document $document): bool
    {
        if (! $user->can('documents.view')) {
            return false;
        }

        return $this->passesConfidentialityGate($user, $document);
    }

    public function userCanDownload(User $user, Document $document): bool
    {
        if (! $user->can('documents.download')) {
            return false;
        }

        return $this->passesConfidentialityGate($user, $document);
    }

    /**
     * Elevated staff may revise files at any stage. Authors and other
     * uploaders may upload a new version after secretariat returns the
     * measure for revision, or when the current version failed processing
     * so a bad OCR/ingest outcome does not leave the filing stuck.
     */
    public function userCanUploadVersion(User $user, Document $document): bool
    {
        if (! $user->can('documents.uploadVersion') || ! $this->userCanView($user, $document)) {
            return false;
        }

        if ($this->hasElevatedRole($user)) {
            return true;
        }

        if ($document->status instanceof ReturnedForRevision) {
            return true;
        }

        return $this->currentVersionFailedProcessing($document);
    }

    private function currentVersionFailedProcessing(Document $document): bool
    {
        if ($document->relationLoaded('currentVersion')) {
            return $document->currentVersion?->processing_status === ProcessingStatus::Failed;
        }

        if ($document->relationLoaded('versions')) {
            /** @var DocumentVersion|null $current */
            $current = $document->versions->firstWhere('is_current', true);

            return $current?->processing_status === ProcessingStatus::Failed;
        }

        return $document->currentVersion()->first()?->processing_status === ProcessingStatus::Failed;
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($this->hasElevatedRole($user)) {
            return $query;
        }

        $roleIds = $user->roles()->pluck('id');
        $committeeIds = $user->committeeMemberships()
            ->where('is_active', true)
            ->pluck('committee_id');

        return $query->where(function (Builder $inner) use ($user, $roleIds, $committeeIds): void {
            $inner->whereIn('confidentiality', [
                Confidentiality::Public->value,
                Confidentiality::Internal->value,
            ])
                ->orWhere('author_id', $user->getKey())
                ->orWhereHas('grants', function (Builder $grantQuery) use ($user, $roleIds, $committeeIds): void {
                    /** @var Builder<DocumentGrant> $grantQuery */
                    $grantQuery->active()->where(function (Builder $target) use ($user, $roleIds, $committeeIds): void {
                        $target->where('user_id', $user->getKey());

                        if ($roleIds->isNotEmpty()) {
                            $target->orWhereIn('role_id', $roleIds);
                        }

                        if ($committeeIds->isNotEmpty()) {
                            $target->orWhereIn('committee_id', $committeeIds);
                        }
                    });
                });
        });
    }

    private function passesConfidentialityGate(User $user, Document $document): bool
    {
        $level = $document->confidentiality instanceof Confidentiality
            ? $document->confidentiality
            : Confidentiality::tryFrom((string) $document->confidentiality) ?? Confidentiality::Internal;

        if (in_array($level, [Confidentiality::Public, Confidentiality::Internal], true)) {
            return true;
        }

        if ($document->author_id === $user->getKey()) {
            return true;
        }

        if ($this->hasElevatedRole($user)) {
            return true;
        }

        return $this->hasActiveGrant($user, $document);
    }

    private function hasElevatedRole(User $user): bool
    {
        return $user->hasAnyRole(self::ELEVATED_ROLES);
    }

    /**
     * Whether the user may act as elevated document staff (secretariat, etc.).
     */
    public function hasElevatedUploadAccess(User $user): bool
    {
        return $this->hasElevatedRole($user);
    }

    private function hasActiveGrant(User $user, Document $document): bool
    {
        $roleIds = $user->roles()->pluck('id');
        $committeeIds = $user->committeeMemberships()
            ->where('is_active', true)
            ->pluck('committee_id');

        return $document->grants()
            ->active()
            ->where(function (Builder $query) use ($user, $roleIds, $committeeIds): void {
                $query->where('user_id', $user->getKey());

                if ($roleIds->isNotEmpty()) {
                    $query->orWhereIn('role_id', $roleIds);
                }

                if ($committeeIds->isNotEmpty()) {
                    $query->orWhereIn('committee_id', $committeeIds);
                }
            })
            ->exists();
    }
}
