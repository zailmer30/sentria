<?php

namespace App\Http\Controllers;

use App\Http\Requests\Committees\StoreCommitteeReportRequest;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\User;
use App\Notifications\CommitteeReportAwaitingChairReview;
use App\Notifications\CommitteeReportSubmitted;
use App\Services\Notifications\InAppNotifier;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommitteeReportController extends Controller
{
    public function __construct(
        private readonly InAppNotifier $notifier,
        private readonly GuardedStateTransition $transitions,
    ) {}

    public function store(StoreCommitteeReportRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $referral = CommitteeReferral::query()->findOrFail($validated['committee_referral_id']);

        $report = CommitteeReport::query()->create([
            'committee_referral_id' => $referral->getKey(),
            'committee_id' => $referral->committee_id,
            'subject_document_id' => $referral->document_id,
            'recommendation' => $validated['recommendation'],
            'findings' => $validated['findings'] ?? null,
            'recommendation_notes' => $validated['recommendation_notes'] ?? null,
            'report_number' => $validated['report_number'] ?: null,
            'status' => 'draft',
            'submitted_by' => $request->user()?->getKey(),
        ]);

        $report->load(['committee', 'subjectDocument']);

        return $this->redirectAfterReportAction($report, 'committees.report_created');
    }

    public function submitForReview(Request $request, CommitteeReport $report): RedirectResponse
    {
        $this->authorize('submitForReview', $report);

        abort_unless($report->status === 'draft', 422);

        $actor = $this->requireUser($request);

        $report->update([
            'status' => 'chair-review',
        ]);

        $report->load(['committee', 'subjectDocument']);

        $this->notifyChairs($report, $actor);

        return $this->redirectAfterReportAction($report, 'committees.report_sent_for_review');
    }

    public function returnToDraft(Request $request, CommitteeReport $report): RedirectResponse
    {
        $this->authorize('returnToDraft', $report);

        abort_unless($report->status === 'chair-review', 422);

        $report->update([
            'status' => 'draft',
        ]);

        $report->load(['committee', 'subjectDocument']);

        return $this->redirectAfterReportAction($report, 'committees.report_returned_to_draft');
    }

    public function submit(Request $request, CommitteeReport $report): RedirectResponse
    {
        $this->authorize('submit', $report);

        abort_unless($report->status === 'chair-review', 422);

        $actor = $this->requireUser($request);

        $report->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $actor->getKey(),
        ]);

        $report->load(['committee', 'subjectDocument.author', 'referral']);

        $this->syncReferralAndDocument($report, $actor);

        if ($report->committee !== null) {
            $recipients = User::permission('referrals.manage')->where('is_active', true)->get();

            if ($report->subjectDocument?->author !== null) {
                $recipients->push($report->subjectDocument->author);
            }

            $this->notifier->send(
                $recipients,
                new CommitteeReportSubmitted($report, $report->committee, $report->subjectDocument),
                $actor,
            );
        }

        return $this->redirectAfterReportAction($report, 'committees.report_submitted');
    }

    public function adopt(Request $request, CommitteeReport $report): RedirectResponse
    {
        $this->authorize('adopt', $report);

        abort_unless($report->status === 'submitted', 422);

        $actor = $this->requireUser($request);

        $report->update([
            'status' => 'adopted',
            'adopted_at' => now(),
        ]);

        $report->load(['committee', 'subjectDocument', 'referral']);

        $this->syncReferralAndDocument($report, $actor);

        return $this->redirectAfterReportAction($report, 'committees.report_adopted');
    }

    private function notifyChairs(CommitteeReport $report, User $actor): void
    {
        $committee = $report->committee;

        if (! $committee instanceof Committee) {
            return;
        }

        $chairs = $committee->memberships()
            ->where('position', 'chair')
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn ($user): bool => $user instanceof User && $user->is_active);

        if ($chairs->isEmpty()) {
            $chairs = User::role('committee-chair')->where('is_active', true)->get();
        }

        $this->notifier->send(
            $chairs,
            new CommitteeReportAwaitingChairReview($report, $committee, $report->subjectDocument),
            $actor,
        );
    }

    private function syncReferralAndDocument(CommitteeReport $report, User $actor): void
    {
        $referral = $report->referral;

        if ($referral instanceof CommitteeReferral && in_array($referral->status, ['pending', 'in-review'], true)) {
            $referral->update([
                'status' => 'reported',
                'completed_at' => $referral->completed_at ?? now(),
            ]);
        }

        $document = $report->subjectDocument;

        if (! $document instanceof Document) {
            return;
        }

        $status = $document->status;

        if ($status instanceof CommitteeReportState) {
            return;
        }

        if ($status instanceof CommitteeReferralState) {
            $this->transitions->transition($document, CommitteeReview::class, $actor);
            $document->refresh();
        }

        if ($document->status instanceof CommitteeReview) {
            $this->transitions->transition($document, CommitteeReportState::class, $actor);
        }
    }

    private function redirectAfterReportAction(CommitteeReport $report, string $flash): RedirectResponse
    {
        if ($report->subjectDocument instanceof Document) {
            return redirect()
                ->route('documents.show', $report->subjectDocument)
                ->with('success', $flash);
        }

        return redirect()
            ->route('committees.show', $report->committee)
            ->with('success', $flash);
    }
}
