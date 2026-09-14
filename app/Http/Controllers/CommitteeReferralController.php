<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Committees\StoreCommitteeReferralRequest;
use App\Http\Requests\Committees\UpdateCommitteeReferralRequest;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentReferralReturned;
use App\Notifications\DocumentReferredToCommittee;
use App\Services\Notifications\InAppNotifier;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReport;
use App\States\Document\CommitteeReview;
use App\States\Document\DocumentWorkflowStatus;
use App\States\Document\Registered;
use Illuminate\Http\RedirectResponse;

class CommitteeReferralController extends Controller
{
    public function __construct(
        private readonly InAppNotifier $notifier,
        private readonly GuardedStateTransition $transitions,
    ) {}

    public function store(StoreCommitteeReferralRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $referral = CommitteeReferral::query()->create([
            ...$validated,
            'status' => 'pending',
            'referred_by' => $request->user()?->getKey(),
            'referred_at' => now(),
        ]);

        $referral->load(['committee', 'document']);

        if ($referral->committee !== null && $referral->document !== null) {
            $this->notifier->send(
                $referral->committee->activeMembers()->get(),
                new DocumentReferredToCommittee($referral->document, $referral->committee, $referral),
                $request->user(),
            );
        }

        return redirect()
            ->route('committees.show', $referral->committee)
            ->with('success', 'committees.referral_created');
    }

    public function update(UpdateCommitteeReferralRequest $request, CommitteeReferral $referral): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $this->requireUser($request);

        if (
            in_array($validated['status'] ?? null, ['reported', 'returned', 'closed'], true)
            && empty($validated['completed_at'])
        ) {
            $validated['completed_at'] = now();
        }

        $referral->update($validated);
        $referral->load(['committee', 'document.author']);

        if ($referral->status === 'in-review') {
            $this->advanceDocument($referral, CommitteeReview::class, $actor);
        }

        if ($referral->status === 'reported') {
            $this->advanceDocument($referral, CommitteeReport::class, $actor);
        }

        if (in_array($referral->status, ['returned', 'closed'], true)) {
            $this->restoreDocumentToRegistered($referral, $actor);
            $this->notifySentBack($referral, $actor);
        }

        return redirect()
            ->route('committees.show', $referral->committee)
            ->with('success', 'committees.referral_updated');
    }

    /**
     * @param  class-string<DocumentWorkflowStatus>  $target
     */
    private function advanceDocument(CommitteeReferral $referral, string $target, User $actor): void
    {
        $document = $referral->document;

        if (! $document instanceof Document) {
            return;
        }

        $status = $document->status;

        if ($target === CommitteeReview::class) {
            if (! $status instanceof CommitteeReferralState) {
                return;
            }
        }

        if ($target === CommitteeReport::class) {
            if (! $status instanceof CommitteeReferralState && ! $status instanceof CommitteeReview) {
                return;
            }

            // Must pass through committee-review when still only referred.
            if ($status instanceof CommitteeReferralState) {
                $this->transitions->transition($document, CommitteeReview::class, $actor);
                $document->refresh();
            }
        }

        if ($document->status instanceof $target) {
            return;
        }

        $this->transitions->transition($document, $target, $actor);
    }

    private function restoreDocumentToRegistered(CommitteeReferral $referral, User $actor): void
    {
        $document = $referral->document;

        if (! $document instanceof Document) {
            return;
        }

        $status = $document->status;

        if (! $status instanceof CommitteeReferralState && ! $status instanceof CommitteeReview) {
            return;
        }

        $this->transitions->transition($document, Registered::class, $actor);
    }

    private function notifySentBack(CommitteeReferral $referral, User $actor): void
    {
        if ($referral->committee === null || $referral->document === null) {
            return;
        }

        $recipients = User::role(UserRole::Secretariat->value)
            ->where('is_active', true)
            ->get();

        if ($referral->document->author !== null) {
            $recipients->push($referral->document->author);
        }

        $this->notifier->send(
            $recipients,
            new DocumentReferralReturned($referral->document, $referral->committee, $referral),
            $actor,
        );
    }
}
