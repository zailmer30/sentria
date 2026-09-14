<?php

namespace App\Services\Legislation;

use App\DTO\Legislation\LegislativeHistoryEvent;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Ordinance;
use App\Models\Vote;
use App\States\Document\Approved;
use App\States\Document\FinalDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LegislativeHistoryService
{
    /**
     * @return list<LegislativeHistoryEvent>
     */
    public function forDocument(Document $document): array
    {
        $document->loadMissing([
            'author',
            'reviewer',
            'returner',
            'registrar',
            'versions.uploader',
            'referrals.committee',
            'referrals.reports',
            'currentVersion',
        ]);

        $events = collect();

        $events->push(new LegislativeHistoryEvent(
            stage: 'document',
            label: 'Document Submitted',
            occurredAt: $document->submitted_at?->toIso8601String(),
            description: $document->title,
            meta: [
                'reference_number' => $document->reference_number,
                'status' => $document->status->label(),
            ],
        ));

        if ($document->reviewed_at !== null) {
            $events->push(new LegislativeHistoryEvent(
                stage: 'secretariat_review',
                label: 'Secretariat Review',
                occurredAt: $document->reviewed_at->toIso8601String(),
                description: 'Document entered secretariat review.',
                meta: [
                    'reviewed_by' => $document->reviewer?->display_name,
                ],
            ));
        }

        if ($document->returned_at !== null) {
            $events->push(new LegislativeHistoryEvent(
                stage: 'returned_for_revision',
                label: 'Returned for Revision',
                occurredAt: $document->returned_at->toIso8601String(),
                description: 'Secretariat returned the document to the author.',
                meta: [
                    'returned_by' => $document->returner?->display_name,
                    'reason' => $document->return_reason,
                ],
            ));
        }

        if ($document->registered_at !== null) {
            $events->push(new LegislativeHistoryEvent(
                stage: 'registered',
                label: 'Registered',
                occurredAt: $document->registered_at->toIso8601String(),
                description: 'Document registered in the legislative record.',
                meta: [
                    'registered_by' => $document->registrar?->display_name,
                ],
            ));
        }

        foreach ($document->referrals->sortBy('referred_at') as $referral) {
            $events->push(new LegislativeHistoryEvent(
                stage: 'committee_referral',
                label: 'Committee Referral',
                occurredAt: $referral->referred_at?->toIso8601String(),
                description: $referral->committee?->name,
                meta: [
                    'committee' => $referral->committee?->name,
                    'status' => $referral->status,
                    'instructions' => $referral->instructions,
                ],
            ));

            foreach ($referral->reports->sortBy('created_at') as $report) {
                $events->push(new LegislativeHistoryEvent(
                    stage: 'committee_report',
                    label: 'Committee Report',
                    occurredAt: ($report->submitted_at ?? $report->created_at)?->toIso8601String(),
                    description: $report->report_number,
                    meta: [
                        'recommendation' => $report->recommendation,
                        'status' => $report->status,
                    ],
                ));
            }
        }

        $agendaItems = AgendaItem::query()
            ->where('document_id', $document->getKey())
            ->with('session')
            ->orderBy('started_at')
            ->get();

        $readingItems = $agendaItems
            ->filter(fn (AgendaItem $item): bool => $this->readingNumber($item) !== null)
            ->groupBy(fn (AgendaItem $item): string => (string) ($this->readingNumber($item) ?? 0));

        foreach ([1, 2, 3] as $reading) {
            $items = $readingItems->get((string) $reading);
            if (! $items instanceof Collection || $items->isEmpty()) {
                continue;
            }

            $item = $this->primaryReadingItem($items);

            $events->push(new LegislativeHistoryEvent(
                stage: $this->readingStage($reading),
                label: $this->readingLabel($reading),
                occurredAt: $this->readingOccurredAt($item),
                description: $item->session?->title,
                meta: [
                    'reading' => $reading,
                    'session_number' => $item->session?->session_number,
                    'agenda_item' => $item->title,
                    'item_number' => $item->item_number,
                    'started_at' => $item->started_at?->toIso8601String(),
                    'completed_at' => $item->completed_at?->toIso8601String(),
                ],
            ));
        }

        foreach ($agendaItems as $item) {
            if ($this->readingNumber($item) !== null) {
                continue;
            }

            $events->push(new LegislativeHistoryEvent(
                stage: 'session',
                label: 'Session Agenda',
                occurredAt: ($item->started_at ?? $item->session?->scheduled_start_at)?->toIso8601String(),
                description: $item->session?->title,
                meta: [
                    'session_number' => $item->session?->session_number,
                    'agenda_item' => $item->title,
                    'item_number' => $item->item_number,
                ],
            ));
        }

        foreach ($document->versions->sortBy('version_number') as $version) {
            if ((int) $version->version_number <= 1) {
                continue;
            }

            $events->push(new LegislativeHistoryEvent(
                stage: 'amendment',
                label: 'Amendment / Version',
                occurredAt: $version->created_at?->toIso8601String(),
                description: 'Version '.$version->version_number,
                meta: [
                    'version_number' => $version->version_number,
                    'uploaded_by' => $version->uploader?->display_name,
                ],
            ));
        }

        $votes = Vote::query()
            ->whereIn('agenda_item_id', $agendaItems->pluck('id'))
            ->with('session')
            ->orderBy('cast_at')
            ->get()
            ->groupBy(fn (Vote $vote): string => ($vote->agenda_item_id ?? '').':'.$vote->voting_round);

        foreach ($votes as $ballots) {
            /** @var Vote $first */
            $first = $ballots->first();

            $current = Vote::latestPerMember($ballots);
            $castAt = $current->sortByDesc(fn (Vote $vote) => $vote->cast_at)->first()?->cast_at;
            $tally = Vote::tallyLatest($ballots);

            $events->push(new LegislativeHistoryEvent(
                stage: 'vote',
                label: 'Official Vote',
                occurredAt: $castAt instanceof Carbon ? $castAt->toIso8601String() : null,
                description: $first->session?->title,
                meta: [
                    'yes' => $tally['yes'],
                    'no' => $tally['no'],
                    'abstain' => $tally['abstain'],
                    'voting_round' => $first->voting_round,
                ],
            ));
        }

        if ($document->status instanceof Approved) {
            $events->push(new LegislativeHistoryEvent(
                stage: 'approval',
                label: 'Approved',
                occurredAt: $document->updated_at?->toIso8601String(),
                description: $document->status->label(),
            ));
        }

        if ($document->status instanceof FinalDocument) {
            $events->push(new LegislativeHistoryEvent(
                stage: 'final_version',
                label: 'Final Version',
                occurredAt: $document->updated_at?->toIso8601String(),
                description: 'Final document recorded.',
            ));
        } elseif ($document->currentVersion instanceof DocumentVersion) {
            $events->push(new LegislativeHistoryEvent(
                stage: 'final_version',
                label: 'Current Version',
                occurredAt: $document->currentVersion->created_at?->toIso8601String(),
                description: 'Version '.$document->currentVersion->version_number,
            ));
        }

        return $this->sortEvents($events);
    }

    private function readingNumber(AgendaItem $item): ?int
    {
        if ($item->reading_number !== null) {
            return (int) $item->reading_number;
        }

        return match ($item->category) {
            'first-reading' => 1,
            'second-reading' => 2,
            'third-reading' => 3,
            default => null,
        };
    }

    private function readingStage(int $reading): string
    {
        return match ($reading) {
            1 => 'first_reading',
            2 => 'second_reading',
            3 => 'third_reading',
            default => 'session',
        };
    }

    private function readingLabel(int $reading): string
    {
        return match ($reading) {
            1 => 'First Reading',
            2 => 'Second Reading',
            3 => 'Third and Final Reading',
            default => 'Reading '.$reading,
        };
    }

    private function readingOccurredAt(AgendaItem $item): ?string
    {
        return ($item->completed_at ?? $item->started_at ?? $item->session?->scheduled_start_at)?->toIso8601String();
    }

    /**
     * @param  Collection<int, AgendaItem>  $items
     */
    private function primaryReadingItem(Collection $items): AgendaItem
    {
        $item = $items->first(fn (AgendaItem $row): bool => $row->completed_at !== null)
            ?? $items->first(fn (AgendaItem $row): bool => $row->started_at !== null)
            ?? $items->sortBy(fn (AgendaItem $row): string => $row->session?->scheduled_start_at?->toIso8601String() ?? '9999')->first()
            ?? $items->first();

        if (! $item instanceof AgendaItem) {
            throw new \LogicException('A reading group must contain at least one agenda item.');
        }

        return $item;
    }

    /**
     * @return list<LegislativeHistoryEvent>
     */
    public function forOrdinance(Ordinance $ordinance): array
    {
        $ordinance->loadMissing('document');

        abort_unless($ordinance->document instanceof Document, 404, 'Ordinance has no linked document.');

        return $this->forDocument($ordinance->document);
    }

    /**
     * @param  Collection<int, LegislativeHistoryEvent>  $events
     * @return list<LegislativeHistoryEvent>
     */
    private function sortEvents(Collection $events): array
    {
        /** @var list<LegislativeHistoryEvent> $sorted */
        $sorted = $events
            ->sortBy(fn (LegislativeHistoryEvent $event): string => $event->occurredAt ?? '9999')
            ->values()
            ->all();

        return $sorted;
    }
}
