<?php

namespace App\Services\Sessions;

use App\Enums\DocumentType;
use App\Enums\SessionType;
use App\Exceptions\TranslatedArgumentException;
use App\Models\AgendaItem;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\Vote;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\AgendaInclusion;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\FinalDocument;
use App\States\Document\Registered;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AgendaService
{
    /** @var list<string> */
    public const ATTACHABLE_CATEGORIES = [
        'first-reading',
        'committee-reports',
        'unfinished-business',
        'business-for-the-day',
        'unassigned-business',
        'third-reading',
        'approval-minutes',
        'referred-measures',
    ];

    public function __construct(
        private readonly HallDisplayService $hall,
        private readonly GuardedStateTransition $transitions,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(LegislativeSession $session, array $attributes): AgendaItem
    {
        return DB::transaction(function () use ($session, $attributes): AgendaItem {
            $hasExplicitPosition = array_key_exists('position', $attributes);

            if (! $hasExplicitPosition) {
                [$position, $parentId] = $this->resolveInsertSlot($session, $attributes);
                $attributes['position'] = $position;
                $attributes['parent_id'] = $attributes['parent_id'] ?? $parentId;
                $this->shiftFrom($session, $position);
            }

            $position = (int) $attributes['position'];
            $parentId = $attributes['parent_id'] ?? null;

            return AgendaItem::query()->create([
                'session_id' => $session->getKey(),
                'parent_id' => $parentId,
                'position' => $position,
                'item_number' => $attributes['item_number'] ?? $this->itemNumberForInsert($session, $parentId, $position),
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'category' => $attributes['category'] ?? 'business-for-the-day',
                'status' => $attributes['status'] ?? 'pending',
                'document_id' => $attributes['document_id'] ?? null,
                'committee_id' => $attributes['committee_id'] ?? null,
                'presented_by' => $attributes['presented_by'] ?? null,
                'time_allotment_minutes' => $attributes['time_allotment_minutes'] ?? null,
                'reading_number' => $attributes['reading_number'] ?? null,
                'requires_vote' => (bool) ($attributes['requires_vote'] ?? false),
            ]);
        });
    }

    /**
     * Place a measure on the order of business. Idempotent for the same session,
     * document, and reading. When a heading is given, the item nests under it.
     */
    public function includeDocument(
        LegislativeSession $session,
        Document $document,
        User $actor,
        ?int $readingNumber = null,
        ?AgendaItem $heading = null,
        bool $authorize = true,
    ): ?AgendaItem {
        if ($authorize) {
            abort_unless($actor->can('create', AgendaItem::class), 403);
            abort_unless($actor->can('view', $session), 403);
        }

        $headingReading = $heading instanceof AgendaItem
            ? $this->readingNumberForHeading($heading)
            : null;
        $reading = $headingReading ?? $readingNumber;

        if ($reading === 3 && ! $document->document_type->requiresThirdReading()) {
            throw new InvalidArgumentException('sessions.calendar.resolutions_skip_third_reading');
        }

        if ($this->alreadyLinked($session, $document, $reading)) {
            return null;
        }

        if ($authorize) {
            $this->assertManualPlenaryPlacement($session, $document);
        }

        $category = $heading instanceof AgendaItem
            ? $this->childCategoryForHeading($heading)
            : match ($reading) {
                2 => 'second-reading',
                3 => 'third-reading',
                default => 'first-reading',
            };

        return $this->createItem($session, [
            'title' => $document->title,
            'document_id' => $document->getKey(),
            'committee_id' => $document->committee_id,
            'category' => $category,
            'parent_id' => $heading?->getKey(),
            'reading_number' => $reading,
            'requires_vote' => ($reading ?? 0) > 1,
        ]);
    }

    /**
     * @param  list<string>  $documentIds
     * @return list<AgendaItem>
     */
    public function bindDocuments(
        LegislativeSession $session,
        AgendaItem $heading,
        array $documentIds,
        User $actor,
    ): array {
        if ($heading->session_id !== $session->getKey()) {
            throw new InvalidArgumentException('Agenda heading does not belong to this session.');
        }

        if (! $this->isAttachableHeading($heading)) {
            throw new InvalidArgumentException('Documents cannot be bound to this agenda heading.');
        }

        if ($heading->category === 'committee-reports') {
            $this->assertFiledCommitteeReports($documentIds);
        }

        return DB::transaction(function () use ($session, $heading, $documentIds, $actor): array {
            $created = [];

            foreach ($documentIds as $documentId) {
                $document = Document::query()->find($documentId);

                if (! $document instanceof Document) {
                    throw new InvalidArgumentException('Document not found.');
                }

                $reading = $this->readingNumberForHeading($heading);

                if ($this->alreadyLinked($session, $document, $reading)) {
                    continue;
                }

                $this->assertManualPlenaryPlacement($session, $document);

                if (! $this->documentEligibleForHeading($document, $heading)) {
                    throw new InvalidArgumentException('Document is not eligible for this agenda heading.');
                }

                if ($this->shouldAdvanceInclusion($document, $heading)) {
                    $document->forceFill(['current_reading' => $reading])->save();
                    $this->transitions->transition($document, AgendaInclusion::class, $actor);
                    $document = $document->fresh() ?? $document;
                }

                $item = $this->includeDocument($session, $document, $actor, $reading, $heading);

                if ($item instanceof AgendaItem) {
                    $created[] = $item;
                }
            }

            return $created;
        });
    }

    public function isAttachableHeading(AgendaItem $heading): bool
    {
        return $heading->document_id === null
            && in_array($heading->category, self::ATTACHABLE_CATEGORIES, true);
    }

    public function attachableHeading(LegislativeSession $session, string $category): ?AgendaItem
    {
        return $session->agendaItems()
            ->where('category', $category)
            ->whereNull('document_id')
            ->orderBy('position')
            ->first();
    }

    /**
     * Move an existing agenda row under another heading without duplicating it.
     */
    public function reparentUnderHeading(
        LegislativeSession $session,
        AgendaItem $item,
        AgendaItem $heading,
        string $childCategory,
        ?int $readingNumber,
    ): AgendaItem {
        if ($item->session_id !== $session->getKey() || $heading->session_id !== $session->getKey()) {
            throw new InvalidArgumentException('Agenda item does not belong to this session.');
        }

        return DB::transaction(function () use ($session, $item, $heading, $childCategory, $readingNumber): AgendaItem {
            $oldPosition = (int) $item->position;
            $item->update(['position' => 0]);

            AgendaItem::query()
                ->where('session_id', $session->getKey())
                ->where('position', '>', $oldPosition)
                ->decrement('position');

            $insertAt = $this->insertPositionAfterChildren($session, $heading);
            $this->shiftFrom($session, $insertAt);

            $siblingCount = $session->agendaItems()
                ->where('parent_id', $heading->getKey())
                ->whereKeyNot($item->getKey())
                ->count();
            $parentNumber = $heading->item_number;

            $item->update([
                'parent_id' => $heading->getKey(),
                'position' => $insertAt,
                'category' => $childCategory,
                'reading_number' => $readingNumber,
                'requires_vote' => ($readingNumber ?? 0) > 1,
                'item_number' => ($parentNumber !== null && $parentNumber !== '')
                    ? $parentNumber.'.'.($siblingCount + 1)
                    : (string) $insertAt,
            ]);

            return $item->fresh() ?? $item;
        });
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorder(LegislativeSession $session, array $orderedIds): void
    {
        DB::transaction(function () use ($session, $orderedIds): void {
            $items = $session->agendaItems()->get()->keyBy(fn (AgendaItem $item): string => $item->getKey());
            $parents = $this->parentsForOrder($orderedIds, $items);
            $itemNumbers = $this->itemNumbersForOrder($orderedIds, $items, $parents);

            foreach ($orderedIds as $index => $id) {
                $item = $items->get($id);

                if (! $item instanceof AgendaItem) {
                    continue;
                }

                $parentId = array_key_exists($id, $parents) ? $parents[$id] : $item->parent_id;
                $payload = [
                    'position' => $index + 1,
                    'item_number' => $itemNumbers[$id] ?? (string) ($index + 1),
                    'parent_id' => $parentId,
                ];

                if ($item->document_id !== null) {
                    $heading = is_string($parentId) && $parentId !== '' ? $items->get($parentId) : null;

                    if ($heading instanceof AgendaItem) {
                        $payload['category'] = $this->childCategoryForHeading($heading);
                        $payload['reading_number'] = $this->readingNumberForHeading($heading);
                    }
                }

                AgendaItem::query()
                    ->where('session_id', $session->getKey())
                    ->whereKey($id)
                    ->update($payload);
            }
        });
    }

    public function advance(LegislativeSession $session): ?AgendaItem
    {
        return DB::transaction(function () use ($session): ?AgendaItem {
            $current = $session->agendaItems()
                ->where('status', 'in-progress')
                ->orderBy('position')
                ->first();

            if ($current instanceof AgendaItem && $current->voting_open_at !== null) {
                throw new InvalidArgumentException('sessions.advance_blocked_voting');
            }

            $next = null;

            if ($current instanceof AgendaItem) {
                $blocked = $this->advanceBlockedReason($session, $current);

                if ($blocked !== null) {
                    throw new InvalidArgumentException($blocked);
                }

                $next = $this->nextItemAfterAdvance($session, $current);
                $this->leaveCurrentItem($session, $current);
            } else {
                $next = $this->nextPendingItem($session);

                if (! $next instanceof AgendaItem) {
                    throw new InvalidArgumentException('sessions.no_next_item');
                }
            }

            $this->hall->clearForAgendaAdvance($session);

            if (! $next instanceof AgendaItem) {
                return null;
            }

            $next->update([
                'status' => 'in-progress',
                'started_at' => $next->started_at ?? now(),
                'completed_at' => null,
            ]);

            return $next->fresh();
        });
    }

    public function advanceBlockedReason(LegislativeSession $session, ?AgendaItem $current = null): ?string
    {
        $current ??= $this->currentItem($session);

        if (! $current instanceof AgendaItem) {
            return $this->nextPendingItem($session) instanceof AgendaItem
                ? null
                : 'sessions.no_next_item';
        }

        if ($current->voting_open_at !== null) {
            return 'sessions.advance_blocked_voting';
        }

        $heading = $this->headingScope($current);
        $laterPending = $this->votableMeasuresInHeading($session, $heading)
            ->filter(fn (AgendaItem $item): bool => $item->getKey() !== $current->getKey()
                && $item->position > $current->position
                && $item->status === 'pending');
        $laterConsidered = $this->votableMeasuresInHeading($session, $heading)
            ->filter(fn (AgendaItem $item): bool => $item->getKey() !== $current->getKey()
                && $item->position > $current->position
                && $item->status === 'considered');
        $anyConsidered = $this->votableMeasuresInHeading($session, $heading)
            ->contains(fn (AgendaItem $item): bool => $item->status === 'considered');
        $currentNeedsVote = $this->isVotableReadingMeasure($current) && ! $this->floorVoteWasHeld($current);
        $deferred = (bool) $session->defer_heading_votes;

        if ($currentNeedsVote) {
            if ($deferred && $laterPending->isNotEmpty()) {
                return null;
            }

            if ($deferred && $laterPending->isEmpty() && $laterConsidered->isEmpty() && $anyConsidered) {
                return 'sessions.advance_blocked_heading_votes';
            }

            if ($this->isSecondReadingMeasure($current) && ! $this->secondReadingVoteWasHeld($current)) {
                return 'sessions.advance_blocked_second_reading_vote';
            }

            if ($this->isThirdReadingMeasure($current) && ! $this->thirdReadingVoteWasHeld($current)) {
                return 'sessions.advance_blocked_third_reading_vote';
            }
        }

        return null;
    }

    /**
     * Offered only when the clerk is on the last votable measure of a heading,
     * later votable siblings are no longer pending, and earlier siblings are
     * `considered`. Skipped pending measures are not pulled into the voting
     * pass — they stay pending until the floor reaches them.
     */
    public function canBeginHeadingVotes(LegislativeSession $session, ?AgendaItem $current = null): bool
    {
        $current ??= $this->currentItem($session);

        if (! $current instanceof AgendaItem || $current->voting_open_at !== null) {
            return false;
        }

        return $this->advanceBlockedReason($session, $current) === 'sessions.advance_blocked_heading_votes';
    }

    /**
     * The floor never leaves the heading: the last measure becomes `considered`,
     * then the first `considered` measure in the heading returns to the floor.
     */
    public function beginHeadingVotes(LegislativeSession $session): AgendaItem
    {
        return DB::transaction(function () use ($session): AgendaItem {
            $current = $this->currentItem($session);

            if (! $current instanceof AgendaItem) {
                throw new InvalidArgumentException('sessions.agenda_empty');
            }

            if ($current->voting_open_at !== null) {
                throw new InvalidArgumentException('sessions.advance_blocked_voting');
            }

            if (! $this->canBeginHeadingVotes($session, $current)) {
                throw new InvalidArgumentException(
                    $this->advanceBlockedReason($session, $current) ?? 'sessions.advance_blocked_heading_votes',
                );
            }

            if ($this->isVotableReadingMeasure($current) && ! $this->floorVoteWasHeld($current)) {
                $current->update([
                    'status' => 'considered',
                    'completed_at' => null,
                ]);
            } else {
                $current->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            $heading = $this->headingScope($current);
            $first = $this->votableMeasuresInHeading($session, $heading)
                ->first(fn (AgendaItem $item): bool => $item->status === 'considered');

            if (! $first instanceof AgendaItem) {
                throw new InvalidArgumentException('sessions.advance_blocked_heading_votes');
            }

            $first->update([
                'status' => 'in-progress',
                'started_at' => $first->started_at ?? now(),
                'completed_at' => null,
            ]);

            $this->hall->clearForAgendaAdvance($session);

            return $first->fresh() ?? $first;
        });
    }

    public function headingScope(AgendaItem $item): AgendaItem
    {
        $item->loadMissing('parent');

        if ($item->document_id === null && in_array($item->category, self::ATTACHABLE_CATEGORIES, true)) {
            return $item;
        }

        $parent = $item->parent;

        if ($parent instanceof AgendaItem) {
            return $parent;
        }

        return $item;
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    public function votableMeasuresInHeading(LegislativeSession $session, AgendaItem $heading): Collection
    {
        return $session->agendaItems()
            ->where('parent_id', $heading->getKey())
            ->orderBy('position')
            ->get()
            ->filter(fn (AgendaItem $item): bool => $this->isVotableReadingMeasure($item))
            ->values();
    }

    public function isVotableReadingMeasure(AgendaItem $item): bool
    {
        return $this->isSecondReadingMeasure($item) || $this->isThirdReadingMeasure($item);
    }

    public function firstConsideredMeasure(LegislativeSession $session, AgendaItem $heading): ?AgendaItem
    {
        return $this->votableMeasuresInHeading($session, $heading)
            ->first(fn (AgendaItem $item): bool => $item->status === 'considered');
    }

    /**
     * The item regular advance would put on the floor. Read-only.
     */
    public function nextItemAfterAdvance(LegislativeSession $session, ?AgendaItem $current = null): ?AgendaItem
    {
        $current ??= $this->currentItem($session);

        if (! $current instanceof AgendaItem) {
            return $this->nextPendingItem($session);
        }

        $heading = $this->headingScope($current);
        $others = $this->votableMeasuresInHeading($session, $heading)
            ->filter(fn (AgendaItem $item): bool => $item->getKey() !== $current->getKey());
        $laterPending = $others->filter(
            fn (AgendaItem $item): bool => $item->position > $current->position && $item->status === 'pending',
        );

        if ($laterPending->isNotEmpty()) {
            return $this->nextPendingItem($session);
        }

        if ($this->isVotableReadingMeasure($current) && ! $this->floorVoteWasHeld($current)) {
            return $this->nextPendingItem($session);
        }

        $nextConsidered = $others
            ->filter(fn (AgendaItem $item): bool => $item->status === 'considered')
            ->sortBy('position')
            ->first();

        if ($nextConsidered instanceof AgendaItem) {
            return $nextConsidered;
        }

        return $this->nextPendingItem($session);
    }

    private function leaveCurrentItem(LegislativeSession $session, AgendaItem $current): void
    {
        $consider = (bool) $session->defer_heading_votes
            && $this->isVotableReadingMeasure($current)
            && ! $this->floorVoteWasHeld($current);

        $current->update($consider
            ? [
                'status' => 'considered',
                'completed_at' => null,
            ]
            : [
                'status' => 'completed',
                'completed_at' => now(),
            ]);
    }

    private function shouldRestoreConsidered(LegislativeSession $session, AgendaItem $current): bool
    {
        if (! $this->isVotableReadingMeasure($current) || $this->floorVoteWasHeld($current)) {
            return false;
        }

        $heading = $this->headingScope($current);
        $others = $this->votableMeasuresInHeading($session, $heading)
            ->filter(fn (AgendaItem $item): bool => $item->getKey() !== $current->getKey());

        if ($others->contains(fn (AgendaItem $item): bool => $item->position > $current->position && $item->status === 'considered')) {
            return true;
        }

        if ($others->isEmpty()) {
            return false;
        }

        return $others->every(fn (AgendaItem $item): bool => $item->status === 'completed');
    }

    public function isSecondReadingMeasure(AgendaItem $item): bool
    {
        return $item->document_id !== null && (int) $item->reading_number === 2;
    }

    public function isThirdReadingMeasure(AgendaItem $item): bool
    {
        if ($item->document_id === null) {
            return false;
        }

        return (int) $item->reading_number === 3 || $item->category === 'third-reading';
    }

    public function secondReadingVoteWasHeld(AgendaItem $item): bool
    {
        return $this->floorVoteWasHeld($item);
    }

    public function thirdReadingVoteWasHeld(AgendaItem $item): bool
    {
        return $this->floorVoteWasHeld($item);
    }

    public function secondReadingVotePassed(AgendaItem $item): bool
    {
        $tally = $this->floorVoteTally($item);

        return $tally !== null && $tally['yes'] > $tally['no'];
    }

    /**
     * @return array{yes: int, no: int}|null
     */
    public function secondReadingTally(AgendaItem $item): ?array
    {
        return $this->floorVoteTally($item);
    }

    private function floorVoteWasHeld(AgendaItem $item): bool
    {
        $tally = $this->floorVoteTally($item);

        return $tally !== null && ($tally['yes'] + $tally['no']) > 0;
    }

    /**
     * @return array{yes: int, no: int}|null
     */
    private function floorVoteTally(AgendaItem $item): ?array
    {
        if ($item->voting_closed_at === null || (int) $item->voting_round < 1) {
            return null;
        }

        $counts = Vote::tallyLatest(
            Vote::query()
                ->where('agenda_item_id', $item->getKey())
                ->where('voting_round', (int) $item->voting_round)
                ->get(),
        );

        return [
            'yes' => $counts['yes'],
            'no' => $counts['no'],
        ];
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    public function prepareStandardTemplate(LegislativeSession $session): Collection
    {
        if ($session->agendaItems()->exists()) {
            throw new InvalidArgumentException('Agenda items already exist for this session.');
        }

        return DB::transaction(function () use ($session): Collection {
            $items = collect();
            $position = 1;

            foreach ($this->standardTemplate($session) as $entry) {
                $parent = $this->createTemplateItem($session, $entry, $position, null);
                $items->push($parent);
                $position++;

                foreach ($entry['children'] ?? [] as $child) {
                    $items->push($this->createTemplateItem($session, $child, $position, $parent->getKey()));
                    $position++;
                }
            }

            return $items;
        });
    }

    /**
     * Place every measure that was referred with a committee meeting date onto
     * a committee hearing. A blank date on the first floor referral waives the
     * hearing and stays on the plenary calendar. Hearings are not bound to one
     * committee, so the docket is the full dated pile — pending and in-review.
     *
     * @return Collection<int, AgendaItem>
     */
    public function includeOpenReferrals(LegislativeSession $session, User $actor): Collection
    {
        if (! $this->isCommitteeHearing($session)) {
            return collect();
        }

        $heading = $this->attachableHeading($session, 'referred-measures');

        if (! $heading instanceof AgendaItem) {
            return collect();
        }

        $documents = Document::query()
            ->whereHas('referrals', function ($query): void {
                $query->whereIn('status', ['pending', 'in-review'])
                    ->where('hearing_waived', false);
            })
            ->orderBy('reference_number')
            ->orderBy('title')
            ->get();

        $created = collect();

        foreach ($documents as $document) {
            $item = $this->includeDocument(
                $session,
                $document,
                $actor,
                readingNumber: null,
                heading: $heading,
                authorize: false,
            );

            if ($item instanceof AgendaItem) {
                $created->push($item);
            }
        }

        return $created;
    }

    /**
     * Place submitted committee reports under Committee Hour / Reports on a
     * regular sitting. Deferral stays in committee and is not queued.
     *
     * @return Collection<int, AgendaItem>
     */
    public function includeSubmittedCommitteeReports(LegislativeSession $session, User $actor): Collection
    {
        if (! $this->isRegularSession($session)) {
            return collect();
        }

        $heading = $this->attachableHeading($session, 'committee-reports');

        if (! $heading instanceof AgendaItem) {
            return collect();
        }

        $created = collect();

        foreach ($this->plenaryReportDocuments() as $document) {
            $item = $this->includeDocument(
                $session,
                $document,
                $actor,
                readingNumber: null,
                heading: $heading,
                authorize: false,
            );

            if ($item instanceof AgendaItem) {
                $created->push($item);
            }
        }

        return $created;
    }

    public function queueSubmittedReport(CommitteeReport $report, User $actor): ?AgendaItem
    {
        if (! $report->isPlenaryRecommendation() || $report->status !== 'submitted') {
            return null;
        }

        $document = $report->subjectDocument;

        if (! $document instanceof Document) {
            return null;
        }

        $session = $this->nextRegularSittingForReports();

        if (! $session instanceof LegislativeSession) {
            return null;
        }

        $heading = $this->attachableHeading($session, 'committee-reports');

        if (! $heading instanceof AgendaItem) {
            return null;
        }

        return $this->includeDocument(
            $session,
            $document,
            $actor,
            readingNumber: null,
            heading: $heading,
            authorize: false,
        );
    }

    public function currentItem(LegislativeSession $session): ?AgendaItem
    {
        return $session->agendaItems()
            ->where('status', 'in-progress')
            ->orderBy('position')
            ->first();
    }

    public function nextPendingItem(LegislativeSession $session): ?AgendaItem
    {
        return $session->agendaItems()
            ->where('status', 'pending')
            ->orderBy('position')
            ->first();
    }

    /**
     * The completed item immediately before the current one, or the last
     * completed item when the sitting has been advanced off the end.
     */
    public function previousCompletedItem(LegislativeSession $session): ?AgendaItem
    {
        $current = $this->currentItem($session);

        $query = $session->agendaItems()
            ->whereIn('status', ['completed', 'considered'])
            ->reorder('position', 'desc');

        if ($current instanceof AgendaItem) {
            $query->where('position', '<', $current->position);
        }

        return $query->first();
    }

    /**
     * Undo an accidental advance: restore the previous completed item to the
     * floor and put the skipped item back to pending.
     */
    public function retreat(LegislativeSession $session): AgendaItem
    {
        return DB::transaction(function () use ($session): AgendaItem {
            $current = $this->currentItem($session);

            if ($current instanceof AgendaItem && $current->voting_open_at !== null) {
                throw new InvalidArgumentException('sessions.retreat_blocked_voting');
            }

            $previous = $this->previousCompletedItem($session);

            if (! $previous instanceof AgendaItem) {
                throw new InvalidArgumentException('sessions.no_previous_item');
            }

            if ($current instanceof AgendaItem) {
                $restoreConsidered = $this->shouldRestoreConsidered($session, $current);

                $current->update($restoreConsidered
                    ? [
                        'status' => 'considered',
                        'completed_at' => null,
                    ]
                    : [
                        'status' => 'pending',
                        'started_at' => null,
                    ]);
            }

            $previous->update([
                'status' => 'in-progress',
                'completed_at' => null,
            ]);

            $this->hall->clearForAgendaAdvance($session);

            return $previous->fresh() ?? $previous;
        });
    }

    /**
     * @return list<array{
     *     category: string,
     *     title: string,
     *     item_number: string,
     *     description?: string,
     *     time_allotment_minutes?: int,
     *     children?: list<array{category: string, title: string, item_number: string, description?: string}>
     * }>
     */
    private function standardTemplate(LegislativeSession $session): array
    {
        if ($this->isCommitteeHearing($session)) {
            return $this->committeeHearingTemplate($session);
        }

        return [
            ['category' => 'call-to-order', 'title' => 'Call to Order', 'item_number' => '1'],
            [
                'category' => 'convocation',
                'title' => 'Invocation',
                'item_number' => '2',
                'description' => $this->convocationDescription($session),
            ],
            ['category' => 'roll-call', 'title' => 'Roll Call', 'item_number' => '3'],
            [
                'category' => 'approval-minutes',
                'title' => 'Reading and Consideration of the Minutes',
                'item_number' => '4',
            ],
            [
                'category' => 'privilege-hour',
                'title' => 'Privilege Hour',
                'item_number' => '5',
                'description' => '10 mins. max per member; floor may be given only once unless allowed by the presiding officer but not oftener than twice.',
                'time_allotment_minutes' => 10,
            ],
            [
                'category' => 'first-reading',
                'title' => 'Reference of Business',
                'item_number' => '6',
            ],
            [
                'category' => 'committee-hour',
                'title' => 'Committee Hour',
                'item_number' => '7',
                'children' => [
                    [
                        'category' => 'committee-reports',
                        'title' => 'Reports',
                        'item_number' => '7.1',
                        'description' => 'To include monthly committee accomplishment report/s of regular and oversight committees.',
                    ],
                    [
                        'category' => 'committee-information',
                        'title' => 'Information',
                        'item_number' => '7.2',
                        'description' => 'Relevance to committee functions required.',
                    ],
                    [
                        'category' => 'committee-trial-balance',
                        'title' => 'SB, VM and Committee Monthly Financial Trial Balance',
                        'item_number' => '7.3',
                        'description' => 'Government transparency compliance.',
                    ],
                ],
            ],
            [
                'category' => 'calendar-of-business',
                'title' => 'Calendar of Business',
                'item_number' => '8',
                'children' => [
                    ['category' => 'unfinished-business', 'title' => 'Unfinished Business', 'item_number' => '8.1'],
                    ['category' => 'business-for-the-day', 'title' => 'Business for the Day', 'item_number' => '8.2'],
                    ['category' => 'unassigned-business', 'title' => 'Unassigned Business', 'item_number' => '8.3'],
                ],
            ],
            [
                'category' => 'third-reading',
                'title' => 'Business on Third and Final Reading',
                'item_number' => '9',
            ],
            [
                'category' => 'other-matters',
                'title' => 'Other Matters / Announcements',
                'item_number' => '10',
                'description' => 'The Sanggunian may discuss matters not included in the calendar of business such as those concerning administrative problems.',
            ],
            ['category' => 'adjournment', 'title' => 'Adjournment', 'item_number' => '11'],
        ];
    }

    /**
     * @return list<array{
     *     category: string,
     *     title: string,
     *     item_number: string,
     *     description?: string,
     *     time_allotment_minutes?: int
     * }>
     */
    private function committeeHearingTemplate(LegislativeSession $session): array
    {
        return [
            ['category' => 'call-to-order', 'title' => 'Call to Order', 'item_number' => '1'],
            [
                'category' => 'convocation',
                'title' => 'Invocation',
                'item_number' => '2',
                'description' => $this->convocationDescription($session),
            ],
            ['category' => 'roll-call', 'title' => 'Roll Call', 'item_number' => '3'],
            [
                'category' => 'referred-measures',
                'title' => 'Committee Concerns',
                'item_number' => '4',
            ],
            [
                'category' => 'other-matters',
                'title' => 'Other Matters',
                'item_number' => '5',
            ],
            ['category' => 'adjournment', 'title' => 'Adjournment', 'item_number' => '6'],
        ];
    }

    private function isCommitteeHearing(LegislativeSession $session): bool
    {
        return SessionType::tryFrom((string) $session->type) === SessionType::CommitteeHearing;
    }

    private function isRegularSession(LegislativeSession $session): bool
    {
        return SessionType::tryFrom((string) $session->type) === SessionType::Regular;
    }

    /**
     * @return Collection<int, Document>
     */
    private function plenaryReportDocuments(): Collection
    {
        return Document::query()
            ->where('status', CommitteeReportState::$name)
            ->whereHas('subjectReports', function ($query): void {
                $query->where('status', 'submitted')
                    ->whereIn('recommendation', CommitteeReport::PLENARY_RECOMMENDATIONS);
            })
            ->with('subjectReports')
            ->orderBy('reference_number')
            ->orderBy('title')
            ->get()
            ->filter(function (Document $document): bool {
                $report = $document->subjectReports
                    ->first(function (CommitteeReport $row): bool {
                        return $row->status === 'submitted'
                            && $row->isPlenaryRecommendation();
                    });

                return $report instanceof CommitteeReport;
            })
            ->values();
    }

    private function nextRegularSittingForReports(): ?LegislativeSession
    {
        return LegislativeSession::query()
            ->where('type', SessionType::Regular->value)
            ->whereNotIn('status', [
                'in-session',
                'suspended',
                'adjourned',
                'minutes-for-review',
                'finalized',
                'archived',
            ])
            ->orderByRaw('scheduled_start_at NULLS LAST')
            ->orderBy('scheduled_start_at')
            ->orderBy('created_at')
            ->get()
            ->first(fn (LegislativeSession $session): bool => $this->attachableHeading($session, 'committee-reports') instanceof AgendaItem);
    }

    /**
     * @param  array{category: string, title: string, item_number: string, description?: string, time_allotment_minutes?: int}  $entry
     */
    private function createTemplateItem(LegislativeSession $session, array $entry, int $position, ?string $parentId): AgendaItem
    {
        return AgendaItem::query()->create([
            'session_id' => $session->getKey(),
            'parent_id' => $parentId,
            'position' => $position,
            'item_number' => $entry['item_number'],
            'title' => $entry['title'],
            'description' => $entry['description'] ?? null,
            'category' => $entry['category'],
            'status' => 'pending',
            'requires_vote' => false,
            'time_allotment_minutes' => $entry['time_allotment_minutes'] ?? 5,
        ]);
    }

    private function convocationDescription(LegislativeSession $session): string
    {
        $hymnAndCreed = "Municipal Hymn and Councilor's Creed";

        if ($this->isFirstRegularSessionOfMonth($session)) {
            return 'Opening Prayer, National Anthem, '.$hymnAndCreed;
        }

        return 'Opening Prayer, '.$hymnAndCreed;
    }

    private function isFirstRegularSessionOfMonth(LegislativeSession $session): bool
    {
        if ($session->type !== SessionType::Regular->value) {
            return false;
        }

        $at = $session->scheduled_start_at ?? now();

        return ! LegislativeSession::query()
            ->whereKeyNot($session->getKey())
            ->where('type', SessionType::Regular->value)
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '<', $at)
            ->whereYear('scheduled_start_at', $at->year)
            ->whereMonth('scheduled_start_at', $at->month)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: int, 1: string|null}
     */
    private function resolveInsertSlot(LegislativeSession $session, array $attributes): array
    {
        $explicitParentId = $attributes['parent_id'] ?? null;

        if (is_string($explicitParentId) && $explicitParentId !== '') {
            $heading = $session->agendaItems()->whereKey($explicitParentId)->first();

            if ($heading instanceof AgendaItem) {
                return [$this->insertPositionAfterChildren($session, $heading), $heading->getKey()];
            }
        }

        $category = (string) ($attributes['category'] ?? 'business-for-the-day');
        $headingCategory = $this->headingCategoryFor($category);

        if ($headingCategory !== null) {
            $heading = $session->agendaItems()
                ->where('category', $headingCategory)
                ->whereNull('document_id')
                ->orderBy('position')
                ->first();

            if ($heading instanceof AgendaItem) {
                return [$this->insertPositionAfterChildren($session, $heading), $heading->getKey()];
            }
        }

        $adjournment = $session->agendaItems()
            ->where('category', 'adjournment')
            ->orderBy('position')
            ->first();

        if ($adjournment instanceof AgendaItem) {
            return [$adjournment->position, null];
        }

        return [((int) ($session->agendaItems()->max('position') ?? 0)) + 1, null];
    }

    private function insertPositionAfterChildren(LegislativeSession $session, AgendaItem $heading): int
    {
        $heading->refresh();

        $lastChild = $session->agendaItems()
            ->where('parent_id', $heading->getKey())
            ->reorder('position', 'desc')
            ->first();

        return $lastChild instanceof AgendaItem
            ? $lastChild->position + 1
            : $heading->position + 1;
    }

    private function headingCategoryFor(string $category): ?string
    {
        return match ($category) {
            'first-reading', 'communications' => 'first-reading',
            'second-reading', 'business-for-the-day', 'new-business' => 'business-for-the-day',
            'third-reading' => 'third-reading',
            'committee-reports', 'committee-report' => 'committee-reports',
            'unfinished-business' => 'unfinished-business',
            'unassigned-business' => 'unassigned-business',
            'approval-minutes' => 'approval-minutes',
            'referred-measures' => 'referred-measures',
            default => null,
        };
    }

    private function shiftFrom(LegislativeSession $session, int $insertAt): void
    {
        AgendaItem::query()
            ->where('session_id', $session->getKey())
            ->where('position', '>=', $insertAt)
            ->increment('position');
    }

    private function itemNumberForInsert(LegislativeSession $session, ?string $parentId, int $position): string
    {
        if ($parentId === null) {
            return (string) $position;
        }

        $parent = AgendaItem::query()->find($parentId);

        if (! $parent instanceof AgendaItem || $parent->item_number === null || $parent->item_number === '') {
            return (string) $position;
        }

        $index = $session->agendaItems()->where('parent_id', $parentId)->count() + 1;

        return $parent->item_number.'.'.$index;
    }

    /**
     * Document rows follow the nearest heading above them in the new order, so
     * a measure moved under item 4 becomes 4.1 instead of keeping 5.1.
     *
     * @param  list<string>  $orderedIds
     * @param  Collection<string, AgendaItem>  $items
     * @return array<string, string|null>
     */
    private function parentsForOrder(array $orderedIds, Collection $items): array
    {
        $parents = [];

        foreach ($orderedIds as $index => $id) {
            $item = $items->get($id);

            if (! $item instanceof AgendaItem) {
                continue;
            }

            if ($item->document_id === null) {
                $parents[$id] = $item->parent_id;

                continue;
            }

            $parents[$id] = $this->nearestHeadingId($orderedIds, $items, $index) ?? $item->parent_id;
        }

        return $parents;
    }

    /**
     * @param  list<string>  $orderedIds
     * @param  Collection<string, AgendaItem>  $items
     */
    private function nearestHeadingId(array $orderedIds, Collection $items, int $index): ?string
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $candidate = $items->get($orderedIds[$i]);

            if (! $candidate instanceof AgendaItem || $candidate->document_id !== null) {
                continue;
            }

            if ($candidate->category === 'adjournment') {
                continue;
            }

            return $candidate->getKey();
        }

        return null;
    }

    /**
     * @param  list<string>  $orderedIds
     * @param  Collection<string, AgendaItem>  $items
     * @param  array<string, string|null>  $parents
     * @return array<string, string>
     */
    private function itemNumbersForOrder(array $orderedIds, Collection $items, array $parents = []): array
    {
        $numbers = [];
        $siblingIndex = [];
        $parentIdOf = function (string $id) use ($items, $parents): ?string {
            if (array_key_exists($id, $parents)) {
                return $parents[$id];
            }

            return $items->get($id)?->parent_id;
        };

        foreach ($orderedIds as $id) {
            $item = $items->get($id);

            if (! $item instanceof AgendaItem) {
                continue;
            }

            $parentId = $parentIdOf($id);
            $isRoot = $parentId === null || $parentId === '' || ! $items->has($parentId);

            if (! $isRoot) {
                continue;
            }

            $siblingIndex[''] = ($siblingIndex[''] ?? 0) + 1;
            $numbers[$id] = (string) $siblingIndex[''];
        }

        $guard = 0;

        while ($guard < 8 && count($numbers) < count($orderedIds)) {
            $progressed = false;

            foreach ($orderedIds as $id) {
                if (isset($numbers[$id])) {
                    continue;
                }

                $item = $items->get($id);

                if (! $item instanceof AgendaItem) {
                    continue;
                }

                $parentId = $parentIdOf($id);

                if ($parentId === null || $parentId === '') {
                    continue;
                }

                $parentNumber = $numbers[$parentId] ?? null;

                if ($parentNumber === null) {
                    continue;
                }

                $siblingIndex[$parentId] = ($siblingIndex[$parentId] ?? 0) + 1;
                $numbers[$id] = $parentNumber.'.'.$siblingIndex[$parentId];
                $progressed = true;
            }

            if (! $progressed) {
                foreach ($orderedIds as $index => $id) {
                    if (! isset($numbers[$id]) && $items->has($id)) {
                        $numbers[$id] = (string) ($index + 1);
                    }
                }

                break;
            }

            $guard++;
        }

        return $numbers;
    }

    private function alreadyLinked(LegislativeSession $session, Document $document, ?int $readingNumber): bool
    {
        $query = AgendaItem::query()
            ->where('session_id', $session->getKey())
            ->where('document_id', $document->getKey());

        if ($readingNumber !== null) {
            $query->where('reading_number', $readingNumber);
        }

        return $query->exists();
    }

    /**
     * Committee Hour only adopts a filed plenary report. Attaching a measure
     * that has none leaves the heading with nothing for the chair to move.
     *
     * @param  list<string>  $documentIds
     */
    private function assertFiledCommitteeReports(array $documentIds): void
    {
        $missing = [];

        foreach ($documentIds as $documentId) {
            $document = Document::query()->find($documentId);

            if (! $document instanceof Document) {
                throw new InvalidArgumentException('Document not found.');
            }

            if ($document->document_type->isMeasure() && $document->wasHeardInCommittee()) {
                continue;
            }

            if (! $this->hasFiledCommitteeReport($document)) {
                $missing[] = $document->title;
            }
        }

        if ($missing === []) {
            return;
        }

        throw new TranslatedArgumentException(
            'sessions.agenda_committee_report_required',
            ['title' => implode(', ', $missing)],
        );
    }

    /**
     * A heard measure stays off regular and special agendas until a plenary
     * report is filed, and that filing is placed automatically under Committee
     * Hour. Committee hearings and public hearings stay open.
     */
    public function assertManualPlenaryPlacement(LegislativeSession $session, Document $document): void
    {
        if (! $this->isPlenarySession($session)) {
            return;
        }

        if (! $document->document_type->isMeasure() || ! $document->wasHeardInCommittee()) {
            return;
        }

        if (! $this->hasFiledCommitteeReport($document)) {
            throw new TranslatedArgumentException(
                'sessions.agenda_heard_report_required',
                ['title' => $document->title],
            );
        }

        if ($document->status instanceof CommitteeReportState) {
            throw new TranslatedArgumentException(
                'sessions.agenda_heard_report_automatic',
                ['title' => $document->title],
            );
        }
    }

    private function isPlenarySession(LegislativeSession $session): bool
    {
        $type = SessionType::tryFrom((string) $session->type);

        return $type === SessionType::Regular || $type === SessionType::Special;
    }

    private function hasFiledCommitteeReport(Document $document): bool
    {
        return $document->subjectReports()
            ->whereIn('status', ['submitted', 'adopted'])
            ->whereIn('recommendation', CommitteeReport::PLENARY_RECOMMENDATIONS)
            ->exists();
    }

    private function documentEligibleForHeading(Document $document, AgendaItem $heading): bool
    {
        $status = $document->status->getValue();

        return match ($heading->category) {
            'first-reading' => $document->status instanceof AgendaInclusion
                && $document->document_type->isMeasure()
                && ($document->current_reading === null || $document->current_reading === 1),
            'business-for-the-day' => $document->status instanceof AgendaInclusion
                && $document->current_reading === 2,
            'third-reading' => $document->document_type->requiresThirdReading()
                && (
                    $document->status instanceof FinalDocument
                    || ($document->status instanceof AgendaInclusion && $document->current_reading === 3)
                ),
            'committee-reports', 'unfinished-business', 'unassigned-business' => in_array($status, [
                'registered',
                'committee-referral',
                'committee-review',
                'committee-report',
                'agenda-inclusion',
                'final-document',
            ], true),
            'approval-minutes' => $document->document_type === DocumentType::Minutes
                && $document->status instanceof Registered,
            'referred-measures' => $document->document_type->isMeasure()
                && $document->referrals()
                    ->whereIn('status', ['pending', 'in-review'])
                    ->exists(),
            default => false,
        };
    }

    private function shouldAdvanceInclusion(Document $document, AgendaItem $heading): bool
    {
        if (! $document->status->canTransitionTo(AgendaInclusion::class)) {
            return false;
        }

        return match ($heading->category) {
            'business-for-the-day' => false,
            'third-reading' => $document->status instanceof FinalDocument,
            default => false,
        };
    }

    private function readingNumberForHeading(AgendaItem $heading): ?int
    {
        return match ($heading->category) {
            'first-reading' => 1,
            'business-for-the-day' => 2,
            'third-reading' => 3,
            default => null,
        };
    }

    private function childCategoryForHeading(AgendaItem $heading): string
    {
        return match ($heading->category) {
            'business-for-the-day' => 'second-reading',
            default => $heading->category,
        };
    }
}
