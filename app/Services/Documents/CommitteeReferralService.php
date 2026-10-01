<?php

namespace App\Services\Documents;

use App\Models\AgendaItem;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Notifications\DocumentReferredToCommittee;
use App\Services\Notifications\InAppNotifier;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReview;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommitteeReferralService
{
    public function __construct(private readonly InAppNotifier $notifier) {}

    /**
     * @param  list<string>  $committeeIds
     */
    public function refer(
        Document $document,
        array $committeeIds,
        User $actor,
        ?LegislativeSession $session = null,
        ?AgendaItem $agendaItem = null,
        ?string $meetingOn = null,
        ?string $remarks = null,
    ): CommitteeReferral {
        $ids = [];

        foreach ($committeeIds as $committeeId) {
            if (is_string($committeeId) && $committeeId !== '' && ! in_array($committeeId, $ids, true)) {
                $ids[] = $committeeId;
            }
        }

        if ($ids === []) {
            throw new InvalidArgumentException('Choose at least one committee before referring.');
        }

        $primaryId = $ids[0];
        $document->forceFill(['committee_id' => $primaryId])->save();

        $primary = null;
        $hearingWaived = $session instanceof LegislativeSession && ($meetingOn === null || $meetingOn === '');

        foreach ($ids as $index => $committeeId) {
            $referral = $this->upsertReferral(
                $document,
                $committeeId,
                $actor,
                $index === 0,
                $session,
                $agendaItem,
                $meetingOn,
                $remarks,
                hearingWaived: $hearingWaived,
            );

            if ($index === 0) {
                $primary = $referral;
            }
        }

        if (! $primary instanceof CommitteeReferral) {
            throw new InvalidArgumentException('Choose at least one committee before referring.');
        }

        return $primary;
    }

    /**
     * Replace the open referral set on a measure that is already with committee.
     *
     * @param  list<string>  $committeeIds
     */
    public function sync(
        Document $document,
        array $committeeIds,
        User $actor,
        ?string $meetingOn = null,
        ?string $remarks = null,
    ): CommitteeReferral {
        $status = $document->status;

        if (! $status instanceof CommitteeReferralState && ! $status instanceof CommitteeReview) {
            throw new InvalidArgumentException('This measure is not with a committee.');
        }

        $ids = [];

        foreach ($committeeIds as $committeeId) {
            if (is_string($committeeId) && $committeeId !== '' && ! in_array($committeeId, $ids, true)) {
                $ids[] = $committeeId;
            }
        }

        if ($ids === []) {
            throw new InvalidArgumentException('Choose at least one committee before referring.');
        }

        $open = CommitteeReferral::query()
            ->where('document_id', $document->getKey())
            ->whereNull('completed_at')
            ->with('reports')
            ->get();

        $locked = $open->first(
            fn (CommitteeReferral $referral): bool => ! in_array((string) $referral->committee_id, $ids, true)
                && $referral->reports->isNotEmpty(),
        );

        if ($locked instanceof CommitteeReferral) {
            throw new InvalidArgumentException('A committee with a report on this measure cannot be removed.');
        }

        $hearingWaived = (bool) ($open->first(fn (CommitteeReferral $referral): bool => $referral->is_primary)?->hearing_waived
            ?? $open->first()?->hearing_waived
            ?? false);

        return DB::transaction(function () use ($document, $ids, $actor, $meetingOn, $remarks, $open, $hearingWaived): CommitteeReferral {
            foreach ($open as $referral) {
                if (in_array((string) $referral->committee_id, $ids, true)) {
                    continue;
                }

                $referral->update([
                    'status' => 'closed',
                    'completed_at' => now(),
                    'is_primary' => false,
                ]);
            }

            $document->forceFill(['committee_id' => $ids[0]])->save();

            $primary = null;

            foreach ($ids as $index => $committeeId) {
                $referral = $this->upsertReferral(
                    $document,
                    $committeeId,
                    $actor,
                    $index === 0,
                    null,
                    null,
                    $meetingOn,
                    $remarks,
                    true,
                    $hearingWaived,
                );

                if ($index === 0) {
                    $primary = $referral;
                }
            }

            if (! $primary instanceof CommitteeReferral) {
                throw new InvalidArgumentException('Choose at least one committee before referring.');
            }

            return $primary;
        });
    }

    private function upsertReferral(
        Document $document,
        string $committeeId,
        User $actor,
        bool $isPrimary,
        ?LegislativeSession $session,
        ?AgendaItem $agendaItem,
        ?string $meetingOn,
        ?string $remarks,
        bool $overwriteDetails = false,
        bool $hearingWaived = false,
    ): CommitteeReferral {
        $existing = CommitteeReferral::query()
            ->where('document_id', $document->getKey())
            ->where('committee_id', $committeeId)
            ->whereNull('completed_at')
            ->first();

        if ($existing instanceof CommitteeReferral) {
            $updates = ['is_primary' => $isPrimary];

            if ($session instanceof LegislativeSession && $existing->session_id === null) {
                $updates['session_id'] = $session->getKey();
            }

            if ($agendaItem instanceof AgendaItem && $existing->agenda_item_id === null) {
                $updates['agenda_item_id'] = $agendaItem->getKey();
            }

            if ($overwriteDetails || $meetingOn !== null) {
                $updates['meeting_on'] = $meetingOn;
            }

            if ($overwriteDetails || $remarks !== null) {
                $updates['instructions'] = $remarks;
            }

            $existing->update($updates);

            return $existing;
        }

        $referral = CommitteeReferral::query()->create([
            'document_id' => $document->getKey(),
            'committee_id' => $committeeId,
            'session_id' => $session?->getKey(),
            'agenda_item_id' => $agendaItem?->getKey(),
            'status' => 'pending',
            'is_primary' => $isPrimary,
            'instructions' => $remarks,
            'meeting_on' => $meetingOn,
            'hearing_waived' => $hearingWaived,
            'referred_by' => $actor->getKey(),
            'referred_at' => now(),
        ]);

        $referral->load(['committee', 'document']);

        if ($referral->committee !== null && $referral->document !== null) {
            $this->notifier->send(
                $referral->committee->activeMembers()->get(),
                new DocumentReferredToCommittee($referral->document, $referral->committee, $referral),
                $actor,
            );
        }

        return $referral;
    }
}
