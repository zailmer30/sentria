<?php

namespace App\Services\Sessions;

use App\Events\AgendaItemChanged;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Documents\CommitteeReferralService;
use App\Services\Documents\DocumentAccessService;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\AgendaInclusion;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReview;
use App\States\Document\ReadingDeliberation;
use App\States\Session\InSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FloorReferralService
{
    public function __construct(
        private readonly GuardedStateTransition $transitions,
        private readonly CommitteeReferralService $referrals,
        private readonly AgendaService $agenda,
        private readonly DocumentAccessService $access,
    ) {}

    /**
     * @param  list<string>  $committeeIds
     */
    public function refer(
        LegislativeSession $session,
        AgendaItem $item,
        array $committeeIds,
        User $actor,
        ?string $meetingOn = null,
        ?string $remarks = null,
    ): Document {
        if (! $actor->can('documents.refer')) {
            throw new AuthorizationException('Missing permission [documents.refer] for this transition.');
        }

        $document = $item->document;

        if ($document instanceof Document && ! $this->access->userCanView($actor, $document)) {
            throw new AuthorizationException('This measure is not available to you.');
        }

        if (! $this->allowsRefer($session, $item, $actor) || ! $document instanceof Document) {
            throw new InvalidArgumentException('This measure cannot be referred from first reading.');
        }

        $canOpenThenRefer = $document->status instanceof AgendaInclusion;

        $referred = DB::transaction(function () use ($session, $item, $document, $committeeIds, $actor, $canOpenThenRefer, $meetingOn, $remarks): Document {
            if ($canOpenThenRefer) {
                $this->transitions->transition($document, ReadingDeliberation::class, $actor);
                $document = $document->fresh() ?? $document;
            }

            $this->referrals->refer($document, $committeeIds, $actor, $session, $item, $meetingOn, $remarks);
            $this->transitions->transition($document, CommitteeReferralState::class, $actor);

            return $document->fresh() ?? $document;
        });

        $current = $this->agenda->currentItem($session);
        event(new AgendaItemChanged($session, $current, $this->agenda->nextPendingItem($session)));

        return $referred;
    }

    public function allowsRefer(LegislativeSession $session, AgendaItem $item, User $actor): bool
    {
        if (! $actor->can('documents.refer') || ! $session->status instanceof InSession) {
            return false;
        }

        if ($item->session_id !== $session->getKey() || $item->reading_number !== 1) {
            return false;
        }

        $document = $item->document;

        if (! $document instanceof Document || (int) ($document->current_reading ?? 1) !== 1) {
            return false;
        }

        if (! $this->access->userCanView($actor, $document)) {
            return false;
        }

        return $document->status instanceof AgendaInclusion
            || $document->status instanceof ReadingDeliberation;
    }

    public function allowsEdit(LegislativeSession $session, AgendaItem $item, User $actor): bool
    {
        if (! $actor->can('documents.refer') || ! $session->status instanceof InSession) {
            return false;
        }

        if ($item->session_id !== $session->getKey()) {
            return false;
        }

        $document = $item->document;

        if (! $document instanceof Document) {
            return false;
        }

        if (! $document->status instanceof CommitteeReferralState && ! $document->status instanceof CommitteeReview) {
            return false;
        }

        if (! $this->access->userCanView($actor, $document)) {
            return false;
        }

        $document->loadMissing('referrals');

        return $document->referrals->contains(
            fn ($referral): bool => in_array($referral->status, ['pending', 'in-review'], true),
        );
    }
}
