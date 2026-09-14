<?php

namespace App\Services\Sessions;

use App\Events\AgendaItemChanged;
use App\Models\AgendaItem;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\AgendaInclusion;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview as CommitteeReviewState;
use App\States\Session\InSession;
use App\States\Session\Suspended;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CalendarRoutingService
{
    /** @var list<string> */
    private const STARTED_STATUSES = [
        'in-session',
        'suspended',
        'adjourned',
        'minutes-for-review',
        'finalized',
        'archived',
    ];

    /** @var list<string> */
    private const ACTIVE_ITEM_STATUSES = ['pending', 'in-progress'];

    /** @var array<string, bool> */
    private array $businessForTheDayOpen = [];

    /** @var array<string, bool> */
    private array $thirdReadingOpen = [];

    /** @var array<string, list<string>> */
    private array $referredIdsBySession = [];

    public function __construct(
        private readonly AgendaService $agenda,
        private readonly HallDisplayService $hall,
        private readonly GuardedStateTransition $transitions,
    ) {}

    public function calendarSecondReading(LegislativeSession $session, AgendaItem $item, User $actor): AgendaItem
    {
        if (! $actor->can('agenda.manage')) {
            throw new AuthorizationException('Missing permission [agenda.manage] for this action.');
        }

        if (! $this->allowsSecondReading($session, $item)) {
            throw new InvalidArgumentException($this->secondReadingBlockReason($session, $item));
        }

        $heading = $this->agenda->attachableHeading($session, 'business-for-the-day');

        if (! $heading instanceof AgendaItem) {
            throw new InvalidArgumentException('sessions.calendar.no_bft_heading');
        }

        return DB::transaction(function () use ($session, $item, $heading, $actor): AgendaItem {
            $this->ensureReadyForSecondReading($item->document, $actor, $session);

            $moved = $this->agenda->reparentUnderHeading(
                $session,
                $item,
                $heading,
                'second-reading',
                2,
            );

            $this->broadcastAgenda($session);

            return $moved;
        });
    }

    public function postpone(LegislativeSession $session, AgendaItem $item, User $actor): AgendaItem
    {
        if (! $actor->can('agenda.manage')) {
            throw new AuthorizationException('Missing permission [agenda.manage] for this action.');
        }

        if (! $this->sessionIsLive($session)) {
            throw new InvalidArgumentException('sessions.calendar.postpone_not_live');
        }

        if (! $this->allowsPostpone($session, $item)) {
            throw new InvalidArgumentException('sessions.calendar.cannot_postpone');
        }

        return $this->markPostponedAndCarry($session, $item, $actor);
    }

    public function undoPostpone(LegislativeSession $session, AgendaItem $item, User $actor): AgendaItem
    {
        if (! $actor->can('agenda.manage')) {
            throw new AuthorizationException('Missing permission [agenda.manage] for this action.');
        }

        if ($item->status !== 'postponed') {
            throw new InvalidArgumentException('sessions.calendar.not_postponed');
        }

        if (! $this->allowsUndo($item)) {
            throw new InvalidArgumentException('sessions.calendar.undo_next_started');
        }

        return DB::transaction(function () use ($session, $item): AgendaItem {
            $carriedId = $item->getAttributes()['carried_to_agenda_item_id'] ?? null;
            $carried = is_string($carriedId) && $carriedId !== ''
                ? AgendaItem::query()->find($carriedId)
                : null;

            if ($carried instanceof AgendaItem) {
                $carried->delete();
            }

            $item->update([
                'status' => 'pending',
                'postponed_at' => null,
                'carried_to_session_id' => null,
                'carried_to_agenda_item_id' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

            $this->broadcastAgenda($session);

            return $item->fresh() ?? $item;
        });
    }

    public function carryLeftoversOnAdjourn(LegislativeSession $session): void
    {
        $actor = $this->agendaManagerFor($session);

        foreach ($this->adjournmentCarryItems($session) as $item) {
            $this->markPostponedAndCarry($session, $item, $actor);
        }
    }

    public function consumeCarryQueue(LegislativeSession $session): void
    {
        $heading = $this->agenda->attachableHeading($session, 'unfinished-business');

        if (! $heading instanceof AgendaItem) {
            return;
        }

        $queued = AgendaItem::query()
            ->with('session')
            ->where('status', 'postponed')
            ->whereNull('carried_to_agenda_item_id')
            ->whereHas('session', function ($query) use ($session): void {
                $query->where('type', $session->type)->whereKeyNot($session->getKey());
            })
            ->orderBy('postponed_at')
            ->orderBy('position')
            ->get();

        foreach ($queued as $item) {
            $source = $item->session;

            if (! $source instanceof LegislativeSession) {
                continue;
            }

            $next = $this->nextSameTypeSitting($source);

            if ($next === null || $next->getKey() !== $session->getKey()) {
                continue;
            }

            $this->bindCarriedItem($item, $session, $heading, $this->agendaManagerFor($source));
        }
    }

    public function businessForTheDayIsOpen(LegislativeSession $session): bool
    {
        $key = $session->getKey();

        if (array_key_exists($key, $this->businessForTheDayOpen)) {
            return $this->businessForTheDayOpen[$key];
        }

        $heading = $this->agenda->attachableHeading($session, 'business-for-the-day');

        if (! $heading instanceof AgendaItem) {
            return $this->businessForTheDayOpen[$key] = false;
        }

        if (! $this->sessionIsLive($session)) {
            return $this->businessForTheDayOpen[$key] = true;
        }

        if (in_array($heading->status, self::ACTIVE_ITEM_STATUSES, true)) {
            return $this->businessForTheDayOpen[$key] = true;
        }

        return $this->businessForTheDayOpen[$key] = $session->agendaItems()
            ->where('parent_id', $heading->getKey())
            ->whereNotNull('document_id')
            ->whereIn('status', self::ACTIVE_ITEM_STATUSES)
            ->exists();
    }

    public function placeDocumentOnSecondReading(LegislativeSession $session, Document $document, User $actor): AgendaItem
    {
        if (! $actor->can('agenda.manage')) {
            throw new AuthorizationException('Missing permission [agenda.manage] for this action.');
        }

        if (! $this->isCalendarReadyMeasure($document, $session)) {
            throw new InvalidArgumentException('sessions.calendar.cannot_second_reading');
        }

        $existing = $this->activeItemForDocument($session, $document);

        if ($existing instanceof AgendaItem) {
            return $this->calendarSecondReading($session, $existing, $actor);
        }

        if (! $this->businessForTheDayIsOpen($session)) {
            throw new InvalidArgumentException('sessions.calendar.bft_closed');
        }

        $heading = $this->agenda->attachableHeading($session, 'business-for-the-day');

        if (! $heading instanceof AgendaItem) {
            throw new InvalidArgumentException('sessions.calendar.no_bft_heading');
        }

        return DB::transaction(function () use ($session, $document, $heading, $actor): AgendaItem {
            $this->ensureReadyForSecondReading($document, $actor, $session);

            $item = $this->agenda->includeDocument($session, $document->fresh() ?? $document, $actor, 2, $heading);

            if (! $item instanceof AgendaItem) {
                throw new InvalidArgumentException('sessions.calendar.cannot_second_reading');
            }

            $this->broadcastAgenda($session);

            return $item;
        });
    }

    public function calendarThirdReading(LegislativeSession $session, AgendaItem $item, User $actor): AgendaItem
    {
        $document = $item->document;

        if (! $document instanceof Document) {
            throw new InvalidArgumentException('sessions.calendar.cannot_third_reading');
        }

        return $this->placeDocumentOnThirdReading($session, $document, $actor);
    }

    public function placeDocumentOnThirdReading(LegislativeSession $session, Document $document, User $actor, bool $authorize = true): AgendaItem
    {
        if ($authorize && ! $actor->can('agenda.manage')) {
            throw new AuthorizationException('Missing permission [agenda.manage] for this action.');
        }

        $session->loadMissing('agendaItems');

        if (! $this->documentPassedSecondReadingThisSitting($session, $document)) {
            throw new InvalidArgumentException('sessions.calendar.cannot_third_reading');
        }

        if ($this->documentAlreadyOnThirdReading($session, $document->getKey())) {
            $heading = $this->agenda->attachableHeading($session, 'third-reading');
            $existing = $session->agendaItems()
                ->where('document_id', $document->getKey())
                ->when(
                    $heading instanceof AgendaItem,
                    fn ($query) => $query->where(function ($inner) use ($heading): void {
                        $inner->where('parent_id', $heading->getKey())
                            ->orWhere('category', 'third-reading')
                            ->orWhere('reading_number', 3);
                    }),
                    fn ($query) => $query->where('reading_number', 3),
                )
                ->first();

            if ($existing instanceof AgendaItem) {
                return $existing;
            }
        }

        if (! $this->thirdReadingIsOpen($session)) {
            throw new InvalidArgumentException('sessions.calendar.third_closed');
        }

        $heading = $this->agenda->attachableHeading($session, 'third-reading');

        if (! $heading instanceof AgendaItem) {
            throw new InvalidArgumentException('sessions.calendar.no_third_heading');
        }

        return DB::transaction(function () use ($session, $document, $heading, $actor): AgendaItem {
            $this->ensureReadyForThirdReading($document, $actor, $session);

            $item = $this->agenda->includeDocument(
                $session,
                $document->fresh() ?? $document,
                $actor,
                3,
                $heading,
                authorize: false,
            );

            if (! $item instanceof AgendaItem) {
                throw new InvalidArgumentException('sessions.calendar.cannot_third_reading');
            }

            $this->broadcastAgenda($session);

            return $item;
        });
    }

    public function placePassedSecondReadingOnThird(LegislativeSession $session, AgendaItem $item, ?User $actor): void
    {
        $document = $item->document;

        if (! $document instanceof Document || ! $this->agenda->secondReadingVotePassed($item)) {
            return;
        }

        $placer = $actor instanceof User && $actor->can('agenda.manage')
            ? $actor
            : $this->agendaManagerFor($session) ?? $actor;

        if (! $placer instanceof User) {
            return;
        }

        try {
            $this->placeDocumentOnThirdReading($session, $document, $placer, authorize: false);
        } catch (InvalidArgumentException) {
            return;
        }
    }

    public function allowsThirdReading(LegislativeSession $session, AgendaItem $item): bool
    {
        if ($item->session_id !== $session->getKey() || $item->document_id === null || $item->status === 'postponed') {
            return false;
        }

        if ($this->isThirdReadingItem($session, $item)) {
            return false;
        }

        if (! $this->agenda->isSecondReadingMeasure($item) && ! $this->isBusinessForTheDayItem($session, $item)) {
            return false;
        }

        if (! $this->agenda->secondReadingVotePassed($item)) {
            return false;
        }

        if ($this->documentAlreadyOnThirdReading($session, $item->document_id)) {
            return false;
        }

        return $this->thirdReadingIsOpen($session);
    }

    public function thirdReadingIsOpen(LegislativeSession $session): bool
    {
        $key = $session->getKey();

        if (array_key_exists($key, $this->thirdReadingOpen)) {
            return $this->thirdReadingOpen[$key];
        }

        $heading = $this->agenda->attachableHeading($session, 'third-reading');

        if (! $heading instanceof AgendaItem) {
            return $this->thirdReadingOpen[$key] = false;
        }

        if (! $this->sessionIsLive($session)) {
            return $this->thirdReadingOpen[$key] = true;
        }

        if (in_array($heading->status, self::ACTIVE_ITEM_STATUSES, true)) {
            return $this->thirdReadingOpen[$key] = true;
        }

        return $this->thirdReadingOpen[$key] = $session->agendaItems()
            ->where('parent_id', $heading->getKey())
            ->whereNotNull('document_id')
            ->whereIn('status', self::ACTIVE_ITEM_STATUSES)
            ->exists();
    }

    public function postponeDocument(LegislativeSession $session, Document $document, User $actor): AgendaItem
    {
        $existing = $this->activeItemForDocument($session, $document);

        if ($existing instanceof AgendaItem) {
            return $this->postpone($session, $existing, $actor);
        }

        if (! $actor->can('agenda.manage')) {
            throw new AuthorizationException('Missing permission [agenda.manage] for this action.');
        }

        if (! $this->sessionIsLive($session)) {
            throw new InvalidArgumentException('sessions.calendar.postpone_not_live');
        }

        if (! $this->isCalendarReadyMeasure($document, $session)) {
            throw new InvalidArgumentException('sessions.calendar.cannot_postpone');
        }

        $unassigned = $this->agenda->attachableHeading($session, 'unassigned-business');

        if (! $unassigned instanceof AgendaItem) {
            throw new InvalidArgumentException('sessions.calendar.cannot_postpone');
        }

        return DB::transaction(function () use ($session, $document, $unassigned, $actor): AgendaItem {
            $item = $this->agenda->includeDocument($session, $document, $actor, 2, $unassigned);

            if (! $item instanceof AgendaItem) {
                throw new InvalidArgumentException('sessions.calendar.cannot_postpone');
            }

            return $this->postpone($session, $item, $actor);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function docket(LegislativeSession $session, User $viewer): array
    {
        $session->loadMissing('agendaItems.document');

        $rows = [];
        $seenDocuments = [];
        $canManage = $viewer->can('agenda.manage');
        $bftOpen = $this->businessForTheDayIsOpen($session);
        $live = $this->sessionIsLive($session);

        foreach ($session->agendaItems->sortBy('position') as $item) {
            if ($item->document_id === null || $this->isThirdReadingItem($session, $item)) {
                continue;
            }

            if (isset($seenDocuments[$item->document_id])) {
                continue;
            }

            $flags = $this->actionFlags($session, $item, $viewer);

            if ($flags['can_second_reading'] || $flags['can_postpone'] || $flags['can_undo'] || $flags['can_third_reading'] || $flags['placed_on_third_reading'] || $item->status === 'postponed') {
                $rows[] = $this->docketRow($item, $item->document, $flags);
                $seenDocuments[$item->document_id] = true;

                continue;
            }

            if (! $this->listsReferredFirstReading($session, $item)) {
                continue;
            }

            $rows[] = $this->docketRow($item, $item->document, [
                'can_second_reading' => $canManage && $bftOpen,
                'can_postpone' => $canManage && $live,
                'can_undo' => false,
                'can_third_reading' => false,
                'placed_on_third_reading' => false,
                'carried_to' => null,
            ], bindToAgendaItem: false);
            $seenDocuments[$item->document_id] = true;
        }

        return $rows;
    }

    public function allowsSecondReading(LegislativeSession $session, AgendaItem $item): bool
    {
        if ($item->session_id !== $session->getKey() || $item->status === 'postponed' || $item->status === 'completed') {
            return false;
        }

        if ($this->isThirdReadingItem($session, $item)) {
            return false;
        }

        if ($item->document_id !== null && $this->documentAlreadyOnThirdReading($session, $item->document_id)) {
            return false;
        }

        if ($this->isUnfinishedDocumentItem($session, $item) || $this->isBusinessForTheDayItem($session, $item)) {
            return false;
        }

        if ($this->isFirstReadingItem($session, $item) && ! $this->firstReadingSectionIsPast($session)) {
            return false;
        }

        if (! $this->isCalendarReadyMeasure($item->document, $session)) {
            return false;
        }

        return $this->businessForTheDayIsOpen($session);
    }

    public function allowsPostpone(LegislativeSession $session, AgendaItem $item): bool
    {
        if ($item->session_id !== $session->getKey() || $item->document_id === null) {
            return false;
        }

        if (! in_array($item->status, self::ACTIVE_ITEM_STATUSES, true)) {
            return false;
        }

        if ($item->voting_open_at !== null) {
            return false;
        }

        if ($this->isThirdReadingItem($session, $item)) {
            return false;
        }

        if ($item->document_id !== null && $this->documentAlreadyOnThirdReading($session, $item->document_id)) {
            return false;
        }

        if ($this->agenda->isSecondReadingMeasure($item) && $this->agenda->secondReadingVoteWasHeld($item)) {
            return false;
        }

        if ($this->isFirstReadingItem($session, $item) && ! $this->firstReadingSectionIsPast($session)) {
            return false;
        }

        if ($this->isUnassignedDocumentItem($session, $item)) {
            return $this->isCalendarReadyMeasure($item->document, $session);
        }

        if ($this->isCalendarReadyMeasure($item->document, $session)) {
            return true;
        }

        return $this->isBusinessForTheDayItem($session, $item)
            || $this->isUnfinishedDocumentItem($session, $item);
    }

    public function allowsUndo(AgendaItem $item, ?LegislativeSession $from = null): bool
    {
        if ($item->status !== 'postponed') {
            return false;
        }

        $carriedToSessionId = $item->getAttributes()['carried_to_session_id'] ?? null;

        $next = is_string($carriedToSessionId) && $carriedToSessionId !== ''
            ? LegislativeSession::query()->find($carriedToSessionId)
            : $this->nextSameTypeSitting($this->sessionForItem($item, $from));

        if (! $next instanceof LegislativeSession) {
            return true;
        }

        return ! $this->sessionHasStarted($next);
    }

    /**
     * @return array{
     *     can_second_reading: bool,
     *     can_postpone: bool,
     *     can_undo: bool,
     *     can_third_reading: bool,
     *     placed_on_third_reading: bool,
     *     carried_to: array{id: string, session_number: string, title: string}|null
     * }
     */
    public function actionFlags(LegislativeSession $session, AgendaItem $item, User $viewer): array
    {
        $item->loadMissing('document');
        $canManage = $viewer->can('agenda.manage');
        $live = $this->sessionIsLive($session);

        return [
            'can_second_reading' => $canManage && $this->allowsSecondReading($session, $item),
            'can_postpone' => $canManage && $live && $this->allowsPostpone($session, $item),
            'can_undo' => $canManage && $this->allowsUndo($item, $session),
            'can_third_reading' => $canManage && $this->allowsThirdReading($session, $item),
            'placed_on_third_reading' => $this->placedOnThirdReading($session, $item),
            'carried_to' => $this->carriedToPayload($item),
        ];
    }

    public function nextSameTypeSitting(?LegislativeSession $from): ?LegislativeSession
    {
        if (! $from instanceof LegislativeSession) {
            return null;
        }
        $anchor = $from->scheduled_start_at ?? $from->created_at;

        return LegislativeSession::query()
            ->where('type', $from->type)
            ->whereKeyNot($from->getKey())
            ->whereNotIn('status', self::STARTED_STATUSES)
            ->where(function ($query) use ($anchor): void {
                $query->where('scheduled_start_at', '>', $anchor)
                    ->orWhere(function ($inner) use ($anchor): void {
                        $inner->whereNull('scheduled_start_at')
                            ->where('created_at', '>', $anchor);
                    });
            })
            ->orderByRaw('scheduled_start_at NULLS LAST')
            ->orderBy('scheduled_start_at')
            ->orderBy('created_at')
            ->first();
    }

    private function markPostponedAndCarry(LegislativeSession $session, AgendaItem $item, ?User $actor): AgendaItem
    {
        return DB::transaction(function () use ($session, $item, $actor): AgendaItem {
            $wasCurrent = $item->status === 'in-progress';

            $item->update([
                'status' => 'postponed',
                'postponed_at' => now(),
                'postponed_from_category' => $this->originCategory($session, $item),
                'postponed_from_parent_id' => $item->parent_id,
                'completed_at' => now(),
            ]);

            $this->ensureReadyForSecondReading($item->document, $actor, $session);

            if ($wasCurrent) {
                $this->startNextPending($session);
            }

            $this->carryToNextSitting($item->fresh() ?? $item, $actor);
            $this->broadcastAgenda($session);

            return $item->fresh() ?? $item;
        });
    }

    private function carryToNextSitting(AgendaItem $item, ?User $actor): void
    {
        $source = $this->sessionForItem($item);

        if (! $source instanceof LegislativeSession) {
            return;
        }

        $next = $this->nextSameTypeSitting($source);

        if (! $next instanceof LegislativeSession) {
            return;
        }

        $heading = $this->agenda->attachableHeading($next, 'unfinished-business');

        if (! $heading instanceof AgendaItem) {
            return;
        }

        $this->bindCarriedItem($item, $next, $heading, $actor);
    }

    private function bindCarriedItem(AgendaItem $from, LegislativeSession $target, AgendaItem $heading, ?User $actor): void
    {
        $document = $from->document;

        if (! $document instanceof Document) {
            return;
        }

        $placed = $this->agenda->includeDocument(
            $target,
            $document,
            $actor ?? $this->agendaManagerFor($target) ?? $this->fallbackActor($from),
            2,
            $heading,
            authorize: false,
        );

        if (! $placed instanceof AgendaItem && $actor === null) {
            return;
        }

        $row = $placed ?? $target->agendaItems()
            ->where('document_id', $document->getKey())
            ->where('parent_id', $heading->getKey())
            ->first();

        if (! $row instanceof AgendaItem) {
            return;
        }

        if ($row->reading_number !== 2) {
            $row->update([
                'reading_number' => 2,
                'requires_vote' => true,
            ]);
        }

        $from->update([
            'carried_to_session_id' => $target->getKey(),
            'carried_to_agenda_item_id' => $row->getKey(),
        ]);
    }

    private function ensureReadyForSecondReading(?Document $document, ?User $actor, LegislativeSession $session): void
    {
        if (! $document instanceof Document) {
            return;
        }

        if ($document->current_reading !== 2) {
            $document->forceFill(['current_reading' => 2])->save();
        }

        if ($document->status instanceof AgendaInclusion) {
            return;
        }

        if (! $document->status->canTransitionTo(AgendaInclusion::class)) {
            return;
        }

        $transitionActor = $actor instanceof User && $actor->can('agenda.manage')
            ? $actor
            : $this->agendaManagerFor($session);

        if (! $transitionActor instanceof User || ! $transitionActor->can('agenda.manage')) {
            return;
        }

        $this->transitions->transition($document, AgendaInclusion::class, $transitionActor);
    }

    private function ensureReadyForThirdReading(?Document $document, ?User $actor, LegislativeSession $session): void
    {
        if (! $document instanceof Document) {
            return;
        }

        if ($document->current_reading !== 3) {
            $document->forceFill(['current_reading' => 3])->save();
        }

        if ($document->status instanceof AgendaInclusion) {
            return;
        }

        if (! $document->status->canTransitionTo(AgendaInclusion::class)) {
            return;
        }

        $transitionActor = $actor instanceof User && $actor->can('agenda.manage')
            ? $actor
            : $this->agendaManagerFor($session);

        if (! $transitionActor instanceof User || ! $transitionActor->can('agenda.manage')) {
            return;
        }

        $this->transitions->transition($document, AgendaInclusion::class, $transitionActor);
    }

    private function startNextPending(LegislativeSession $session): void
    {
        $this->hall->clearForAgendaAdvance($session);

        $next = $this->agenda->nextPendingItem($session);

        if (! $next instanceof AgendaItem) {
            return;
        }

        $next->update([
            'status' => 'in-progress',
            'started_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    private function adjournmentCarryItems(LegislativeSession $session): Collection
    {
        $session->loadMissing('agendaItems.document');

        return $session->agendaItems
            ->filter(function (AgendaItem $item) use ($session): bool {
                if (! in_array($item->status, self::ACTIVE_ITEM_STATUSES, true) || $item->document_id === null) {
                    return false;
                }

                if ($this->isUnassignedDocumentItem($session, $item)) {
                    return $this->isCalendarReadyMeasure($item->document, $session);
                }

                if ($this->isBusinessForTheDayItem($session, $item) && $this->agenda->secondReadingVotePassed($item)) {
                    return false;
                }

                return $this->isBusinessForTheDayItem($session, $item)
                    || $this->isUnfinishedDocumentItem($session, $item);
            })
            ->sortBy('position')
            ->values();
    }

    private function isCalendarReadyMeasure(?Document $document, ?LegislativeSession $session = null): bool
    {
        if (! $document instanceof Document || ! $document->document_type->isMeasure()) {
            return false;
        }

        if ($document->status instanceof CommitteeReportState) {
            return true;
        }

        if ($document->status instanceof AgendaInclusion && $document->current_reading === 2) {
            return true;
        }

        return $session instanceof LegislativeSession
            && $this->wasReferredThisSitting($session, $document);
    }

    private function wasReferredThisSitting(LegislativeSession $session, Document $document): bool
    {
        $referralOnSitting = in_array($document->getKey(), $this->referredDocumentIds($session), true);

        if ($referralOnSitting) {
            return true;
        }

        if (! $this->hasFirstReadingItem($session, $document)) {
            return false;
        }

        $status = $document->status;

        return $document->committee_id !== null
            || $status instanceof CommitteeReferralState
            || $status instanceof CommitteeReviewState
            || $status instanceof CommitteeReportState;
    }

    private function hasFirstReadingItem(LegislativeSession $session, Document $document): bool
    {
        return $session->agendaItems->contains(
            fn (AgendaItem $item): bool => $item->document_id === $document->getKey()
                && $this->isFirstReadingItem($session, $item),
        );
    }

    /**
     * @return list<string>
     */
    private function referredDocumentIds(LegislativeSession $session): array
    {
        $key = $session->getKey();

        if (! array_key_exists($key, $this->referredIdsBySession)) {
            $this->referredIdsBySession[$key] = CommitteeReferral::query()
                ->where('session_id', $session->getKey())
                ->pluck('document_id')
                ->all();
        }

        return $this->referredIdsBySession[$key];
    }

    private function listsReferredFirstReading(LegislativeSession $session, AgendaItem $item): bool
    {
        $document = $item->document;

        if (! $document instanceof Document || ! $this->isFirstReadingItem($session, $item)) {
            return false;
        }

        if ($this->sessionIsLive($session) && ! $this->firstReadingSectionIsPast($session)) {
            return false;
        }

        if (! $this->wasReferredThisSitting($session, $document)) {
            return false;
        }

        return ! $this->documentAlreadyRoutedOnSitting($session, $document->getKey());
    }

    private function firstReadingSectionIsPast(LegislativeSession $session): bool
    {
        $heading = $this->agenda->attachableHeading($session, 'first-reading');

        if (! $heading instanceof AgendaItem) {
            return true;
        }

        if (in_array($heading->status, self::ACTIVE_ITEM_STATUSES, true)) {
            return false;
        }

        return ! $session->agendaItems->contains(function (AgendaItem $item) use ($session): bool {
            if ($item->document_id === null || ! in_array($item->status, self::ACTIVE_ITEM_STATUSES, true)) {
                return false;
            }

            return $this->isFirstReadingItem($session, $item);
        });
    }

    private function isFirstReadingItem(LegislativeSession $session, AgendaItem $item): bool
    {
        return $item->reading_number === 1
            || $item->category === 'first-reading'
            || $this->itemSitsUnder($session, $item, 'first-reading');
    }

    private function documentAlreadyRoutedOnSitting(LegislativeSession $session, string $documentId): bool
    {
        return $session->agendaItems->contains(function (AgendaItem $item) use ($session, $documentId): bool {
            if ($item->document_id !== $documentId) {
                return false;
            }

            return ! $this->isFirstReadingItem($session, $item);
        });
    }

    /**
     * @param  array{
     *     can_second_reading: bool,
     *     can_postpone: bool,
     *     can_undo: bool,
     *     can_third_reading: bool,
     *     placed_on_third_reading?: bool,
     *     carried_to: array{id: string, session_number: string, title: string}|null
     * }  $flags
     * @return array<string, mixed>
     */
    private function docketRow(AgendaItem $item, ?Document $document, array $flags, bool $bindToAgendaItem = true): array
    {
        return [
            'id' => $bindToAgendaItem ? $item->getKey() : $item->document_id,
            'agenda_item_id' => $bindToAgendaItem ? $item->getKey() : null,
            'document_id' => $item->document_id,
            'item_number' => $item->item_number,
            'title' => $document?->title ?? $item->title,
            'reference_number' => $document?->reference_number,
            'status' => $bindToAgendaItem ? $item->status : 'pending',
            'can_second_reading' => $flags['can_second_reading'],
            'can_postpone' => $flags['can_postpone'],
            'can_undo' => $flags['can_undo'],
            'can_third_reading' => $flags['can_third_reading'] ?? false,
            'placed_on_third_reading' => $flags['placed_on_third_reading'] ?? false,
            'carried_to' => $flags['carried_to'],
            'document' => $document ? [
                'title' => $document->title,
                'slug' => $document->slug,
            ] : null,
        ];
    }

    private function placedOnThirdReading(LegislativeSession $session, AgendaItem $item): bool
    {
        if ($item->document_id === null || $this->isThirdReadingItem($session, $item) || $this->isFirstReadingItem($session, $item)) {
            return false;
        }

        return $this->documentAlreadyOnThirdReading($session, $item->document_id);
    }

    private function activeItemForDocument(LegislativeSession $session, Document $document): ?AgendaItem
    {
        return $session->agendaItems()
            ->where('document_id', $document->getKey())
            ->whereIn('status', self::ACTIVE_ITEM_STATUSES)
            ->orderBy('position')
            ->first();
    }

    private function isThirdReadingItem(LegislativeSession $session, AgendaItem $item): bool
    {
        return $this->itemSitsUnder($session, $item, 'third-reading')
            || $item->category === 'third-reading'
            || (int) $item->reading_number === 3;
    }

    private function documentAlreadyOnThirdReading(LegislativeSession $session, string $documentId): bool
    {
        $heading = $this->agenda->attachableHeading($session, 'third-reading');

        return $session->agendaItems()
            ->where('document_id', $documentId)
            ->where(function ($query) use ($heading): void {
                $query->where('category', 'third-reading')
                    ->orWhere('reading_number', 3);

                if ($heading instanceof AgendaItem) {
                    $query->orWhere('parent_id', $heading->getKey());
                }
            })
            ->exists();
    }

    private function documentPassedSecondReadingThisSitting(LegislativeSession $session, Document $document): bool
    {
        $session->loadMissing('agendaItems');

        return $session->agendaItems->contains(
            fn (AgendaItem $item): bool => $item->document_id === $document->getKey()
                && $item->status !== 'postponed'
                && ($this->agenda->isSecondReadingMeasure($item) || $this->isBusinessForTheDayItem($session, $item))
                && $this->agenda->secondReadingVotePassed($item),
        );
    }

    private function isUnassignedDocumentItem(LegislativeSession $session, AgendaItem $item): bool
    {
        return $this->itemSitsUnder($session, $item, 'unassigned-business');
    }

    private function isBusinessForTheDayItem(LegislativeSession $session, AgendaItem $item): bool
    {
        return $this->itemSitsUnder($session, $item, 'business-for-the-day')
            || $item->category === 'second-reading';
    }

    private function isUnfinishedDocumentItem(LegislativeSession $session, AgendaItem $item): bool
    {
        return $this->itemSitsUnder($session, $item, 'unfinished-business');
    }

    private function itemSitsUnder(LegislativeSession $session, AgendaItem $item, string $headingCategory): bool
    {
        if ($item->document_id === null) {
            return false;
        }

        if ($item->category === $headingCategory) {
            return true;
        }

        $heading = $this->agenda->attachableHeading($session, $headingCategory);

        return $heading instanceof AgendaItem && $item->parent_id === $heading->getKey();
    }

    private function originCategory(LegislativeSession $session, AgendaItem $item): string
    {
        if ($this->isUnassignedDocumentItem($session, $item)) {
            return 'unassigned-business';
        }

        if ($this->isBusinessForTheDayItem($session, $item)) {
            return 'business-for-the-day';
        }

        return 'unfinished-business';
    }

    private function secondReadingBlockReason(LegislativeSession $session, AgendaItem $item): string
    {
        if ($this->isUnfinishedDocumentItem($session, $item) || $this->isBusinessForTheDayItem($session, $item)) {
            return 'sessions.calendar.cannot_second_reading';
        }

        if (! $this->isCalendarReadyMeasure($item->document, $session)) {
            return 'sessions.calendar.cannot_second_reading';
        }

        if (! $this->businessForTheDayIsOpen($session)) {
            return 'sessions.calendar.bft_closed';
        }

        return 'sessions.calendar.cannot_second_reading';
    }

    /**
     * @return array{id: string, session_number: string, title: string}|null
     */
    private function carriedToPayload(AgendaItem $item): ?array
    {
        if ($item->status !== 'postponed') {
            return null;
        }

        $attrs = $item->getAttributes();
        $carriedToSessionId = $attrs['carried_to_session_id'] ?? null;

        if (! is_string($carriedToSessionId) || $carriedToSessionId === '') {
            return null;
        }

        $next = LegislativeSession::query()->find($carriedToSessionId);

        if ($next instanceof LegislativeSession) {
            return [
                'id' => $next->getKey(),
                'session_number' => $next->session_number,
                'title' => $next->title,
            ];
        }

        return null;
    }

    private function sessionIsLive(LegislativeSession $session): bool
    {
        return $session->status instanceof InSession || $session->status instanceof Suspended;
    }

    private function sessionHasStarted(LegislativeSession $session): bool
    {
        return in_array($session->status->getValue(), self::STARTED_STATUSES, true);
    }

    private function agendaManagerFor(?LegislativeSession $session): ?User
    {
        if (! $session instanceof LegislativeSession) {
            return null;
        }

        $session->loadMissing('secretary');

        $secretary = $session->secretary;

        if ($secretary instanceof User && $secretary->can('agenda.manage')) {
            return $secretary;
        }

        return null;
    }

    private function sessionForItem(AgendaItem $item, ?LegislativeSession $known = null): ?LegislativeSession
    {
        if ($known instanceof LegislativeSession && $known->getKey() === $item->session_id) {
            return $known;
        }

        if ($item->relationLoaded('session')) {
            $loaded = $item->getRelation('session');

            return $loaded instanceof LegislativeSession ? $loaded : null;
        }

        return LegislativeSession::query()->find($item->session_id);
    }

    private function fallbackActor(AgendaItem $item): User
    {
        $session = $this->sessionForItem($item);
        $session?->loadMissing('secretary');

        if ($session?->secretary instanceof User) {
            return $session->secretary;
        }

        $user = User::query()->first();

        if (! $user instanceof User) {
            throw new InvalidArgumentException('sessions.calendar.no_actor');
        }

        return $user;
    }

    private function broadcastAgenda(LegislativeSession $session): void
    {
        $session->refresh();
        $current = $this->agenda->currentItem($session);
        $next = $this->agenda->nextPendingItem($session);
        event(new AgendaItemChanged($session, $current, $next));
    }
}
