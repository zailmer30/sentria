<?php

namespace App\Services\Sessions;

use App\Enums\SessionType;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\Vote;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\AgendaInclusion;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview as CommitteeReviewState;
use App\States\Document\FinalDocument;
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

        if ($this->alreadyLinked($session, $document, $reading)) {
            return null;
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
            $itemNumbers = $this->itemNumbersForOrder($orderedIds, $items);

            foreach ($orderedIds as $index => $id) {
                if (! $items->has($id)) {
                    continue;
                }

                AgendaItem::query()
                    ->where('session_id', $session->getKey())
                    ->whereKey($id)
                    ->update([
                        'position' => $index + 1,
                        'item_number' => $itemNumbers[$id] ?? (string) ($index + 1),
                    ]);
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

            if ($current instanceof AgendaItem) {
                $blocked = $this->advanceBlockedReason($session, $current);

                if ($blocked !== null) {
                    throw new InvalidArgumentException($blocked);
                }

                $current->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            $next = $session->agendaItems()
                ->where('status', 'pending')
                ->orderBy('position')
                ->first();

            $this->hall->clearForAgendaAdvance($session);

            if (! $next instanceof AgendaItem) {
                return null;
            }

            $next->update([
                'status' => 'in-progress',
                'started_at' => now(),
            ]);

            return $next->fresh();
        });
    }

    public function advanceBlockedReason(LegislativeSession $session, ?AgendaItem $current = null): ?string
    {
        $current ??= $this->currentItem($session);

        if (! $current instanceof AgendaItem) {
            return null;
        }

        if ($current->voting_open_at !== null) {
            return 'sessions.advance_blocked_voting';
        }

        if ($this->isSecondReadingMeasure($current) && ! $this->secondReadingVoteWasHeld($current)) {
            return 'sessions.advance_blocked_second_reading_vote';
        }

        if ($this->isThirdReadingMeasure($current) && ! $this->thirdReadingVoteWasHeld($current)) {
            return 'sessions.advance_blocked_third_reading_vote';
        }

        return null;
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
            ->where('status', 'completed')
            ->orderByDesc('position');

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
                $current->update([
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
        return [
            ['category' => 'call-to-order', 'title' => 'Call to Order', 'item_number' => '1'],
            ['category' => 'opening-prayer', 'title' => 'Opening Prayer', 'item_number' => '2'],
            ['category' => 'anthem', 'title' => $this->anthemTitle($session), 'item_number' => '3'],
            ['category' => 'roll-call', 'title' => 'Roll Call', 'item_number' => '4'],
            [
                'category' => 'approval-minutes',
                'title' => 'Reading and Approval of the Minutes of the Previous Session',
                'item_number' => '5',
            ],
            [
                'category' => 'privilege-hour',
                'title' => 'Privilege Hour',
                'item_number' => '6',
                'description' => '10 mins. max per member; floor may be given only once unless allowed by the presiding officer but not oftener than twice.',
                'time_allotment_minutes' => 10,
            ],
            [
                'category' => 'first-reading',
                'title' => 'First Reading and Referral to Committee',
                'item_number' => '7',
            ],
            [
                'category' => 'committee-hour',
                'title' => 'Committee Hour',
                'item_number' => '8',
                'children' => [
                    [
                        'category' => 'committee-reports',
                        'title' => 'Reports',
                        'item_number' => '8.1',
                        'description' => 'To include monthly committee accomplishment report/s of regular and oversight committees.',
                    ],
                    [
                        'category' => 'committee-information',
                        'title' => 'Information',
                        'item_number' => '8.2',
                        'description' => 'Relevance to committee functions required.',
                    ],
                    [
                        'category' => 'committee-trial-balance',
                        'title' => 'SB, VM and Committee Monthly Financial Trial Balance',
                        'item_number' => '8.3',
                        'description' => 'Government transparency compliance.',
                    ],
                ],
            ],
            [
                'category' => 'calendar-of-business',
                'title' => 'Calendar of Business',
                'item_number' => '9',
                'children' => [
                    ['category' => 'unfinished-business', 'title' => 'Unfinished Business', 'item_number' => '9.1'],
                    ['category' => 'business-for-the-day', 'title' => 'Business for the Day', 'item_number' => '9.2'],
                    ['category' => 'unassigned-business', 'title' => 'Unassigned Business', 'item_number' => '9.3'],
                ],
            ],
            [
                'category' => 'third-reading',
                'title' => 'Business on Third and Final Reading',
                'item_number' => '10',
            ],
            [
                'category' => 'other-matters',
                'title' => 'Other Matters / Announcements',
                'item_number' => '11',
                'description' => 'The Sanggunian may discuss matters not included in the calendar of business such as those concerning administrative problems.',
            ],
            ['category' => 'adjournment', 'title' => 'Adjournment', 'item_number' => '12'],
        ];
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

    private function anthemTitle(LegislativeSession $session): string
    {
        $hymnAndCreed = "Municipal Hymn and Councilor's Creed";

        if ($this->isFirstRegularSessionOfMonth($session)) {
            return 'National Anthem, '.$hymnAndCreed;
        }

        return $hymnAndCreed;
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
        $lastChild = $session->agendaItems()
            ->where('parent_id', $heading->getKey())
            ->orderByDesc('position')
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
     * @param  list<string>  $orderedIds
     * @param  Collection<string, AgendaItem>  $items
     * @return array<string, string>
     */
    private function itemNumbersForOrder(array $orderedIds, Collection $items): array
    {
        $numbers = [];
        $siblingIndex = [];

        foreach ($orderedIds as $id) {
            $item = $items->get($id);

            if (! $item instanceof AgendaItem) {
                continue;
            }

            $parentId = $item->parent_id;
            $isRoot = $parentId === null || ! $items->has($parentId);

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

                if (! $item instanceof AgendaItem || $item->parent_id === null) {
                    continue;
                }

                $parentNumber = $numbers[$item->parent_id] ?? null;

                if ($parentNumber === null) {
                    continue;
                }

                $parentId = $item->parent_id;
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

    private function documentEligibleForHeading(Document $document, AgendaItem $heading): bool
    {
        $status = $document->status->getValue();

        return match ($heading->category) {
            'first-reading' => $document->status instanceof AgendaInclusion
                && $document->document_type->isMeasure()
                && ($document->current_reading === null || $document->current_reading === 1),
            'business-for-the-day' => $document->status instanceof CommitteeReportState
                || $document->status instanceof CommitteeReferralState
                || $document->status instanceof CommitteeReviewState
                || ($document->status instanceof AgendaInclusion && $document->current_reading === 2),
            'third-reading' => $document->status instanceof FinalDocument
                || ($document->status instanceof AgendaInclusion && $document->current_reading === 3),
            'committee-reports', 'unfinished-business', 'unassigned-business' => in_array($status, [
                'registered',
                'committee-referral',
                'committee-review',
                'committee-report',
                'agenda-inclusion',
                'final-document',
            ], true),
            default => false,
        };
    }

    private function shouldAdvanceInclusion(Document $document, AgendaItem $heading): bool
    {
        if (! $document->status->canTransitionTo(AgendaInclusion::class)) {
            return false;
        }

        return match ($heading->category) {
            'business-for-the-day' => $document->status instanceof CommitteeReportState
                || $document->status instanceof CommitteeReferralState
                || $document->status instanceof CommitteeReviewState,
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
