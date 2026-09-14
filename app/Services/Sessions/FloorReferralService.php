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

    public function refer(LegislativeSession $session, AgendaItem $item, string $committeeId, User $actor): Document
    {
        if (! $actor->can('documents.refer')) {
            throw new AuthorizationException('Missing permission [documents.refer] for this transition.');
        }

        if (! $session->status instanceof InSession) {
            throw new InvalidArgumentException('Referral from the floor is only available while the sitting is in session.');
        }

        $current = $this->agenda->currentItem($session);

        if ($current === null || $current->getKey() !== $item->getKey()) {
            throw new InvalidArgumentException('Referral from the floor is only for the item currently on the floor.');
        }

        if ($item->reading_number !== 1) {
            throw new InvalidArgumentException('Referral from the floor is only for first reading.');
        }

        $document = $item->document;

        if ($document === null) {
            throw new InvalidArgumentException('This item has no measure to refer.');
        }

        if (! $this->access->userCanView($actor, $document)) {
            throw new AuthorizationException('This measure is not available to you.');
        }

        $reading = (int) ($document->current_reading ?? 1);

        if ($reading !== 1) {
            throw new InvalidArgumentException('Referral from the floor is only for first reading.');
        }

        $status = $document->status;
        $canOpenThenRefer = $status instanceof AgendaInclusion;
        $canRefer = $status instanceof ReadingDeliberation;

        if (! $canOpenThenRefer && ! $canRefer) {
            throw new InvalidArgumentException('This measure cannot be referred from first reading.');
        }

        $referred = DB::transaction(function () use ($session, $item, $document, $committeeId, $actor, $canOpenThenRefer): Document {
            if ($canOpenThenRefer) {
                $this->transitions->transition($document, ReadingDeliberation::class, $actor);
                $document = $document->fresh() ?? $document;
            }

            $this->referrals->refer($document, $committeeId, $actor, $session, $item);
            $this->transitions->transition($document, CommitteeReferralState::class, $actor);

            return $document->fresh() ?? $document;
        });

        $current = $this->agenda->currentItem($session);
        event(new AgendaItemChanged($session, $current, $this->agenda->nextPendingItem($session)));

        return $referred;
    }
}
