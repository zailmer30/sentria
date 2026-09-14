<?php

namespace App\Services\Documents;

use App\Models\AgendaItem;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Notifications\DocumentReferredToCommittee;
use App\Services\Notifications\InAppNotifier;

class CommitteeReferralService
{
    public function __construct(private readonly InAppNotifier $notifier) {}

    public function refer(
        Document $document,
        string $committeeId,
        User $actor,
        ?LegislativeSession $session = null,
        ?AgendaItem $agendaItem = null,
    ): CommitteeReferral {
        $document->forceFill(['committee_id' => $committeeId])->save();

        $existing = CommitteeReferral::query()
            ->where('document_id', $document->getKey())
            ->where('committee_id', $committeeId)
            ->whereNull('completed_at')
            ->first();

        if ($existing instanceof CommitteeReferral) {
            $updates = [];

            if ($session instanceof LegislativeSession && $existing->session_id === null) {
                $updates['session_id'] = $session->getKey();
            }

            if ($agendaItem instanceof AgendaItem && $existing->agenda_item_id === null) {
                $updates['agenda_item_id'] = $agendaItem->getKey();
            }

            if ($updates !== []) {
                $existing->update($updates);
            }

            return $existing;
        }

        $referral = CommitteeReferral::query()->create([
            'document_id' => $document->getKey(),
            'committee_id' => $committeeId,
            'session_id' => $session?->getKey(),
            'agenda_item_id' => $agendaItem?->getKey(),
            'status' => 'pending',
            'is_primary' => true,
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
