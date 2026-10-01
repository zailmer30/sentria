<?php

namespace App\Services\Workflow;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\Publication;
use App\Models\User;
use App\Notifications\DocumentEnteredCommitteeReview;
use App\Notifications\DocumentWorkflowOutcome;
use App\Notifications\SessionScheduled;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\InAppNotifier;
use App\States\Document\Approved;
use App\States\Document\Archive;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReview as CommitteeReviewState;
use App\States\Document\DocumentWorkflowStatus;
use App\States\Document\Registered;
use App\States\Document\Rejected;
use App\States\Document\SecretariatReview as DocumentSecretariatReview;
use App\States\Minutes\MinutesStatus;
use App\States\Publication\MarkPublic;
use App\States\Publication\PublicationReview;
use App\States\Publication\PublicationWorkflowStatus;
use App\States\Publication\Published;
use App\States\Publication\SecretariatReview;
use App\States\Session\Scheduled;
use App\States\Session\SessionStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Spatie\ModelStates\State;

/**
 * Single entry point for session, document, minutes, and publication workflow transitions.
 * Controllers must not assign raw status strings.
 *
 * AI inherits the authenticated user's permissions: pass that user as $actor.
 * Never call this with a privileged system actor for AI-initiated work.
 */
class GuardedStateTransition
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InAppNotifier $notifier,
    ) {}

    /**
     * @param  class-string<SessionStatus>|class-string<DocumentWorkflowStatus>|class-string<MinutesStatus>|class-string<PublicationWorkflowStatus>  $to
     */
    public function transition(Model $model, string $to, User $actor, string $attribute = 'status'): Model
    {
        $current = $model->{$attribute};

        if (! $current instanceof State) {
            throw new InvalidArgumentException("Model attribute [{$attribute}] is not a workflow state.");
        }

        if (! $current instanceof SessionStatus
            && ! $current instanceof DocumentWorkflowStatus
            && ! $current instanceof MinutesStatus
            && ! $current instanceof PublicationWorkflowStatus) {
            throw new InvalidArgumentException("Model attribute [{$attribute}] is not a workflow state.");
        }

        $fromName = $current->getValue();
        $toName = $to::getMorphClass();
        $permissions = $this->permissionsFor($to, $current, $model);

        if ($permissions !== [] && ! $this->actorHasAnyPermission($actor, $permissions)) {
            $permission = implode('|', $permissions);

            $this->audit->record(
                event: 'workflow.transition.denied',
                category: 'workflow',
                auditable: $model,
                actor: $actor,
                old: ['status' => $fromName],
                new: ['status' => $toName],
                context: ['permission' => $permission, 'reason' => 'permission_denied'],
                message: 'Transition denied: missing permission.',
            );

            throw new AuthorizationException("Missing permission [{$permission}] for this transition.");
        }

        try {
            $current->transitionTo($to);
        } catch (TransitionNotFound $exception) {
            $this->audit->record(
                event: 'workflow.transition.invalid',
                category: 'workflow',
                auditable: $model,
                actor: $actor,
                old: ['status' => $fromName],
                new: ['status' => $toName],
                context: ['reason' => 'invalid_transition'],
                message: 'Invalid workflow transition attempted.',
            );

            throw $exception;
        }

        if ($model instanceof Publication) {
            $this->applyPublicationSideEffects($model, $actor, $toName);
        }

        if ($model instanceof Document) {
            $this->applyDocumentSideEffects($model, $actor, $toName);
            $this->notifyDocumentTransition($model, $toName, $actor);
        }

        if ($model instanceof LegislativeSession) {
            $this->notifySessionTransition($model, $toName, $actor);
        }

        $model->refresh();
        $next = $model->{$attribute};
        $nextName = $next instanceof State ? $next->getValue() : (string) $next;

        $this->audit->record(
            event: 'workflow.transition',
            category: 'workflow',
            auditable: $model,
            actor: $actor,
            old: ['status' => $fromName],
            new: ['status' => $nextName],
            message: 'Workflow transition completed.',
        );

        return $model;
    }

    /**
     * @param  class-string<SessionStatus>|class-string<DocumentWorkflowStatus>|class-string<MinutesStatus>|class-string<PublicationWorkflowStatus>  $to
     * @return list<string>
     */
    private function permissionsFor(string $to, State $from, Model $model): array
    {
        if (is_subclass_of($to, DocumentWorkflowStatus::class)) {
            $fromClass = $from instanceof DocumentWorkflowStatus ? $from::class : null;
            $document = $model instanceof Document ? $model : null;

            return DocumentWorkflowStatus::permissionsFor($to, $fromClass, $document);
        }

        $permission = match (true) {
            is_subclass_of($to, SessionStatus::class) => SessionStatus::permissionFor(
                $to,
                $from instanceof SessionStatus ? $from::class : null,
            ),
            is_subclass_of($to, MinutesStatus::class) => MinutesStatus::permissionFor($to),
            is_subclass_of($to, PublicationWorkflowStatus::class) => PublicationWorkflowStatus::permissionFor($to),
            default => null,
        };

        return $permission === null ? [] : [$permission];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actorHasAnyPermission(User $actor, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($actor->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function applyDocumentSideEffects(Document $document, User $actor, string $toName): void
    {
        if ($toName === DocumentSecretariatReview::$name && $document->reviewed_at === null) {
            $document->forceFill([
                'reviewed_at' => now(),
                'reviewed_by' => $actor->getKey(),
            ])->save();
        }

        if ($toName === Registered::$name && $document->registered_at === null) {
            $document->forceFill([
                'registered_at' => now(),
                'registered_by' => $actor->getKey(),
            ])->save();
        }

        if ($toName === Archive::$name && $document->archived_at === null) {
            $document->forceFill(['archived_at' => now()])->save();
        }
    }

    private function notifyDocumentTransition(Document $document, string $toName, User $actor): void
    {
        if (in_array($toName, [CommitteeReferralState::$name, CommitteeReviewState::$name], true)) {
            $document->loadMissing('committee');

            if ($document->committee !== null) {
                $this->notifier->send(
                    $document->committee->activeMembers()->get(),
                    new DocumentEnteredCommitteeReview($document, $document->committee, $toName),
                    $actor,
                );
            }

            return;
        }

        if (in_array($toName, [Approved::$name, Rejected::$name], true)) {
            $document->loadMissing('author');

            $this->notifier->send(
                $document->author,
                new DocumentWorkflowOutcome($document, $toName),
                $actor,
            );
        }
    }

    private function notifySessionTransition(LegislativeSession $session, string $toName, User $actor): void
    {
        if ($toName !== Scheduled::$name) {
            return;
        }

        $recipients = User::role([
            UserRole::BoardMember->value,
            UserRole::PresidingOfficer->value,
        ])->where('is_active', true)->get();

        $session->loadMissing('presidingOfficer');

        if ($session->presidingOfficer instanceof User) {
            $recipients->push($session->presidingOfficer);
        }

        $this->notifier->send($recipients, new SessionScheduled($session), $actor);
    }

    private function applyPublicationSideEffects(Publication $publication, User $actor, string $toName): void
    {
        if (in_array($toName, [SecretariatReview::$name, PublicationReview::$name], true)) {
            $publication->forceFill([
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();
        }

        if (! in_array($toName, [MarkPublic::$name, Published::$name], true)) {
            return;
        }

        $now = now();

        $publication->forceFill([
            'published_at' => $now,
            'published_by' => $actor->getKey(),
            'unpublished_at' => null,
        ])->save();

        $document = $publication->document;

        if ($document !== null) {
            $document->forceFill([
                'is_public' => true,
                'published_at' => $now,
            ])->save();
        }
    }
}
