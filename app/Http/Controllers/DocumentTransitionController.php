<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Documents\DocumentTransitionRequest;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentResubmitted;
use App\Notifications\DocumentReturnedForRevision;
use App\Notifications\DocumentWorkflowOutcome;
use App\Services\Documents\CommitteeReferralService;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\MeasureSubmissionValidator;
use App\Services\Notifications\InAppNotifier;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\AgendaInclusion;
use App\States\Document\Archive;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview;
use App\States\Document\FinalDocument;
use App\States\Document\Registered;
use App\States\Document\ReturnedForRevision;
use App\States\Document\SecretariatReview;
use App\States\Document\Submitted;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DocumentTransitionController extends Controller
{
    public function __construct(
        private readonly GuardedStateTransition $transitions,
        private readonly InAppNotifier $notifier,
        private readonly DocumentAccessService $access,
        private readonly MeasureSubmissionValidator $submission,
        private readonly CommitteeReferralService $referrals,
    ) {}

    public function __invoke(DocumentTransitionRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('transition', $document);

        $target = $request->targetStateClass();
        $actor = $this->requireUser($request);
        $wasReturned = $document->status instanceof ReturnedForRevision;
        $fromCommitteeReview = $document->status instanceof CommitteeReview;

        if ($target === Submitted::class && $wasReturned) {
            $this->assertCanResubmit($document, $actor);
        }

        if (in_array($target, [SecretariatReview::class, Registered::class], true)) {
            $this->submission->assertReadyForSecretary($document);
        }

        if ($target === ReturnedForRevision::class) {
            $document->forceFill([
                'return_reason' => $request->validated('return_reason'),
                'returned_at' => now(),
                'returned_by' => $actor->getKey(),
                'reviewed_at' => null,
                'reviewed_by' => null,
            ])->save();
        }

        if ($target === CommitteeReferralState::class) {
            $this->referrals->refer(
                $document,
                $request->committeeIds(),
                $actor,
                meetingOn: $request->meetingOn(),
                remarks: $request->remarks(),
            );
        }

        if ($target === AgendaInclusion::class) {
            DB::transaction(function () use ($document, $actor, $target): void {
                $document->forceFill(['current_reading' => $this->readingNumberForInclusion($document)])->save();
                $this->transitions->transition($document, $target, $actor);
            });
        } else {
            $this->transitions->transition($document, $target, $actor);
        }

        if ($target === ReturnedForRevision::class) {
            $this->notifyReturned($document->fresh(), $actor);
        }

        if ($target === Submitted::class && $wasReturned) {
            $this->afterResubmit($document->fresh(), $actor);
        }

        if ($target === CommitteeReview::class) {
            $this->syncOpenReferral($document, 'in-review');
        }

        if ($target === CommitteeReportState::class) {
            $this->syncOpenReferral($document, 'reported');
        }

        if ($target === Archive::class && $fromCommitteeReview) {
            $this->syncOpenReferral($document, 'closed');
            $this->notifyShelved($document->fresh(), $actor);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.transitioned');
    }

    private function readingNumberForInclusion(Document $document): int
    {
        return match (true) {
            $document->status instanceof CommitteeReportState => 2,
            $document->status instanceof FinalDocument => $document->document_type->requiresThirdReading()
                ? 3
                : max(1, (int) ($document->current_reading ?? 2)),
            $document->status instanceof Registered => 1,
            default => max(1, (int) ($document->current_reading ?? 1)),
        };
    }

    private function assertCanResubmit(Document $document, User $actor): void
    {
        $isAuthor = $document->author_id !== null
            && (string) $document->author_id === (string) $actor->getKey();

        if ($isAuthor || $this->access->hasElevatedUploadAccess($actor)) {
            return;
        }

        throw new AuthorizationException('Only the author may resubmit a returned document.');
    }

    private function notifyReturned(Document $document, User $actor): void
    {
        $document->loadMissing('author');

        $reason = (string) ($document->return_reason ?? '');

        $this->notifier->send(
            $document->author,
            new DocumentReturnedForRevision($document, $reason),
            $actor,
        );
    }

    private function notifyShelved(Document $document, User $actor): void
    {
        $document->loadMissing('author');

        $this->notifier->send(
            $document->author,
            new DocumentWorkflowOutcome($document, Archive::$name),
            $actor,
        );
    }

    private function afterResubmit(Document $document, User $actor): void
    {
        $document->forceFill([
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
        ])->save();

        $document->loadMissing('author');

        $this->notifier->send(
            User::role(UserRole::Secretariat->value)->where('is_active', true)->get(),
            new DocumentResubmitted($document),
            $actor,
        );
    }

    private function syncOpenReferral(Document $document, string $status): void
    {
        $referral = CommitteeReferral::query()
            ->where('document_id', $document->getKey())
            ->whereNull('completed_at')
            ->when(
                $document->committee_id !== null,
                fn ($query) => $query->where('committee_id', $document->committee_id),
            )
            ->latest('referred_at')
            ->first();

        if ($referral === null) {
            return;
        }

        if ($status === 'in-review' && $referral->status === 'pending') {
            $referral->update(['status' => 'in-review']);
        }

        if ($status === 'reported' && in_array($referral->status, ['pending', 'in-review'], true)) {
            $referral->update([
                'status' => 'reported',
                'completed_at' => $referral->completed_at ?? now(),
            ]);
        }

        if ($status === 'closed' && in_array($referral->status, ['pending', 'in-review'], true)) {
            $referral->update([
                'status' => 'closed',
                'completed_at' => $referral->completed_at ?? now(),
            ]);
        }
    }
}
