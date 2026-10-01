<?php

namespace App\Services\Sessions;

use App\Enums\VoteChoice;
use App\Events\VoteCast;
use App\Events\VotingClosed;
use App\Events\VotingOpened;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use App\Models\Vote;
use App\Services\Audit\AuditLogger;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\Approved;
use App\States\Document\ReadingDeliberation;
use App\States\Document\Rejected;
use App\States\Document\Voting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

class VotingService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly VotingSettings $settings,
        private readonly GuardedStateTransition $transitions,
        private readonly HallDisplayService $hall,
    ) {}

    public function openVoting(
        LegislativeSession $session,
        AgendaItem $agendaItem,
        User $actor,
        ?Motion $motion = null,
        bool $silent = false,
    ): AgendaItem {
        abort_unless($agendaItem->session_id === $session->getKey(), 422);

        if ($agendaItem->voting_open_at !== null) {
            throw new InvalidArgumentException('Voting is already open for this agenda item.');
        }

        if ($motion !== null) {
            abort_unless($motion->session_id === $session->getKey(), 422);
            abort_unless($motion->agenda_item_id === $agendaItem->getKey(), 422);
        }

        $nextRound = $this->nextRound($session, $agendaItem);

        $openedAt = now();
        $silentRounds = array_map('intval', $agendaItem->silent_voting_rounds ?? []);

        if ($silent) {
            $silentRounds[] = $nextRound;
            $silentRounds = array_values(array_unique($silentRounds));
        } else {
            $silentRounds = array_values(array_filter(
                $silentRounds,
                fn (int $round): bool => $round !== $nextRound,
            ));
        }

        DB::transaction(function () use ($agendaItem, $motion, $nextRound, $openedAt, $silentRounds): void {
            $agendaItem->update([
                'voting_round' => $nextRound,
                'voting_open_at' => $openedAt,
                'voting_opened_at' => $openedAt,
                'silent_voting_rounds' => $silentRounds,
            ]);

            if ($motion !== null) {
                $motion->update([
                    'status' => 'voting_open',
                    'voting_round' => $nextRound,
                ]);
            }
        });

        $refreshed = $agendaItem->fresh();
        if ($refreshed === null) {
            throw new InvalidArgumentException('Agenda item not found after opening voting.');
        }

        $this->audit->record(
            event: 'voting.opened',
            category: 'session',
            auditable: $refreshed,
            actor: $actor,
            new: [
                'voting_round' => $nextRound,
                'motion_id' => $motion?->getKey(),
                'silent' => $silent,
            ],
            message: $silent ? 'Silent electronic voting opened.' : 'Electronic voting opened.',
        );

        event(new VotingOpened(
            $session,
            $refreshed,
            $motion,
            $nextRound,
            $this->settings->electronicIsBinding(),
            $silent,
        ));

        $this->hall->clearPinnedResults($session);

        return $refreshed;
    }

    public function castVote(
        User $user,
        LegislativeSession $session,
        AgendaItem $agendaItem,
        VoteChoice $choice,
        int $votingRound,
    ): Vote {
        abort_unless($agendaItem->session_id === $session->getKey(), 422);

        if ($agendaItem->voting_open_at === null || (int) $agendaItem->voting_round !== $votingRound) {
            throw new InvalidArgumentException('Voting is not open for this round.');
        }

        $attributes = [
            'session_id' => $session->getKey(),
            'agenda_item_id' => $agendaItem->getKey(),
            'voting_round' => $votingRound,
            'user_id' => $user->getKey(),
        ];

        return DB::transaction(function () use ($attributes, $user, $choice, $session, $agendaItem, $votingRound): Vote {
            AgendaItem::query()->whereKey($agendaItem->getKey())->lockForUpdate()->first();

            $latest = Vote::query()
                ->where($attributes)
                ->orderByDesc('cast_at')
                ->orderByDesc('id')
                ->first();

            if ($latest instanceof Vote && $latest->choice === $choice->value) {
                return $latest;
            }

            $vote = Vote::query()->create([
                ...$attributes,
                'choice' => $choice->value,
                'method' => (string) config('sentria.voting.default_method', 'electronic'),
                'cast_at' => now(),
                'ip_address' => request()?->ip(),
            ]);

            $changed = $latest instanceof Vote;
            $this->audit->record(
                event: $changed ? 'vote.changed' : 'vote.cast',
                category: 'session',
                auditable: $vote,
                actor: $user,
                new: [
                    'choice' => $choice->value,
                    'previous_choice' => $changed ? $latest->choice : null,
                    'voting_round' => $votingRound,
                    'agenda_item_id' => $agendaItem->getKey(),
                ],
                message: $changed ? 'Electronic ballot changed.' : 'Electronic ballot cast.',
            );

            event(new VoteCast(
                $session,
                $agendaItem,
                $votingRound,
                $this->tallies($session, $agendaItem, $votingRound),
            ));

            return $vote;
        });
    }

    /**
     * @return array{yes: int, no: int, abstain: int, inhibit: int, total: int}
     */
    public function closeVoting(
        LegislativeSession $session,
        AgendaItem $agendaItem,
        User $actor,
        ?Motion $motion = null,
    ): array {
        abort_unless($agendaItem->session_id === $session->getKey(), 422);

        if ($agendaItem->voting_open_at === null) {
            throw new InvalidArgumentException('Voting is not open for this agenda item.');
        }

        $round = (int) $agendaItem->voting_round;
        $tallies = $this->tallies($session, $agendaItem, $round);

        $closedAt = now();

        DB::transaction(function () use ($agendaItem, $motion, $tallies, $closedAt): void {
            $agendaItem->update([
                'voting_open_at' => null,
                'voting_closed_at' => $closedAt,
            ]);

            if ($motion !== null && $motion->status === 'voting_open') {
                $motion->update([
                    'status' => $tallies['yes'] > $tallies['no'] ? 'carried' : 'lost',
                    'disposed_at' => now(),
                ]);
            }
        });

        $this->audit->record(
            event: 'voting.closed',
            category: 'session',
            auditable: $agendaItem,
            actor: $actor,
            new: ['voting_round' => $round, 'tallies' => $tallies],
            message: 'Electronic voting closed.',
        );

        event(new VotingClosed($session, $agendaItem, $round, $tallies));

        $this->hall->clearPinnedResults($session);

        $this->promotePassedSecondReading($session, $agendaItem, $actor);
        $this->applySecondReadingOutcome($session, $agendaItem, $actor, $tallies);
        $this->applyThirdReadingOutcome($session, $agendaItem, $actor, $tallies);

        return $tallies;
    }

    /**
     * Bring an IRP record in line with a final-reading vote that already closed.
     */
    public function syncCompletedThirdReading(Document $document, User $actor): Document
    {
        if ($document->status instanceof Approved || $document->status instanceof Rejected) {
            return $document;
        }

        $thirdItem = $this->latestClosedReadingItem($document, thirdReading: true);

        if ($thirdItem instanceof AgendaItem) {
            return $this->syncClosedItem($document, $thirdItem, $actor, thirdReading: true);
        }

        if ($document->document_type->requiresThirdReading()) {
            return $document;
        }

        $secondItem = $this->latestClosedReadingItem($document, thirdReading: false);

        if (! $secondItem instanceof AgendaItem) {
            return $document;
        }

        return $this->syncClosedItem($document, $secondItem, $actor, thirdReading: false);
    }

    private function promotePassedSecondReading(LegislativeSession $session, AgendaItem $agendaItem, User $actor): void
    {
        if (! app(AgendaService::class)->isSecondReadingMeasure($agendaItem)) {
            return;
        }

        $agendaItem->loadMissing('document');
        $document = $agendaItem->document;

        if (! $document instanceof Document || ! $document->document_type->requiresThirdReading()) {
            return;
        }

        app(CalendarRoutingService::class)->placePassedSecondReadingOnThird($session, $agendaItem, $actor);
    }

    /**
     * @param  array{yes: int, no: int, abstain: int, inhibit: int, total: int}  $tallies
     */
    private function applySecondReadingOutcome(
        LegislativeSession $session,
        AgendaItem $agendaItem,
        User $actor,
        array $tallies,
    ): void {
        $agenda = app(AgendaService::class);

        if (! $agenda->isSecondReadingMeasure($agendaItem)) {
            return;
        }

        $agendaItem->loadMissing('document');
        $document = $agendaItem->document;

        if (! $document instanceof Document || $document->document_type->requiresThirdReading()) {
            return;
        }

        if ($this->documentHasThirdReadingItem($document)) {
            return;
        }

        $this->applyVoteOutcome($session, $agendaItem, $actor, $tallies);
    }

    /**
     * @param  array{yes: int, no: int, abstain: int, inhibit: int, total: int}  $tallies
     */
    private function applyThirdReadingOutcome(
        LegislativeSession $session,
        AgendaItem $agendaItem,
        User $actor,
        array $tallies,
    ): void {
        if (! app(AgendaService::class)->isThirdReadingMeasure($agendaItem)) {
            return;
        }

        $this->applyVoteOutcome($session, $agendaItem, $actor, $tallies);
    }

    /**
     * @param  array{yes: int, no: int, abstain: int, inhibit: int, total: int}  $tallies
     */
    private function applyVoteOutcome(
        LegislativeSession $session,
        AgendaItem $agendaItem,
        User $actor,
        array $tallies,
    ): void {
        if (($tallies['yes'] + $tallies['no']) < 1) {
            return;
        }

        $agendaItem->loadMissing('document');
        $document = $agendaItem->document;

        if (! $document instanceof Document) {
            return;
        }

        $document = $document->fresh() ?? $document;
        $target = $tallies['yes'] > $tallies['no'] ? Approved::class : Rejected::class;

        if ($document->status instanceof $target) {
            return;
        }

        $manager = $this->legislationManagerFor($session, $actor);

        if (! $manager instanceof User) {
            return;
        }

        try {
            $this->advanceDocumentTo($document, $target, $manager);
        } catch (AuthorizationException|InvalidArgumentException|TransitionNotFound) {
            return;
        }
    }

    private function syncClosedItem(
        Document $document,
        AgendaItem $item,
        User $actor,
        bool $thirdReading,
    ): Document {
        if ((int) $item->voting_round < 1) {
            return $document;
        }

        $session = $item->session;

        if (! $session instanceof LegislativeSession) {
            return $document;
        }

        $tallies = $this->tallies($session, $item, (int) $item->voting_round);

        if ($thirdReading) {
            $this->applyThirdReadingOutcome($session, $item, $actor, $tallies);
        } else {
            $this->applySecondReadingOutcome($session, $item, $actor, $tallies);
        }

        return $document->fresh() ?? $document;
    }

    private function latestClosedReadingItem(Document $document, bool $thirdReading): ?AgendaItem
    {
        return AgendaItem::query()
            ->with('session')
            ->where('document_id', $document->getKey())
            ->whereNotNull('voting_closed_at')
            ->where(function ($query) use ($thirdReading): void {
                if ($thirdReading) {
                    $query->where('reading_number', 3)
                        ->orWhere('category', 'third-reading');

                    return;
                }

                $query->where('reading_number', 2);
            })
            ->orderByDesc('voting_closed_at')
            ->first();
    }

    private function documentHasThirdReadingItem(Document $document): bool
    {
        return AgendaItem::query()
            ->where('document_id', $document->getKey())
            ->where(function ($query): void {
                $query->where('reading_number', 3)
                    ->orWhere('category', 'third-reading');
            })
            ->exists();
    }

    /**
     * @param  class-string<Approved|Rejected>  $target
     */
    private function advanceDocumentTo(Document $document, string $target, User $actor): void
    {
        $document = $document->fresh() ?? $document;

        if ($document->status instanceof $target) {
            return;
        }

        if ($document->status->canTransitionTo($target)) {
            $this->transitions->transition($document, $target, $actor);

            return;
        }

        foreach ([ReadingDeliberation::class, Voting::class] as $waypoint) {
            $document = $document->fresh() ?? $document;

            if ($document->status instanceof $target) {
                return;
            }

            if ($document->status->canTransitionTo($waypoint)) {
                $this->transitions->transition($document, $waypoint, $actor);
            }
        }

        $document = $document->fresh() ?? $document;

        if ($document->status->canTransitionTo($target)) {
            $this->transitions->transition($document, $target, $actor);
        }
    }

    private function legislationManagerFor(LegislativeSession $session, User $actor): ?User
    {
        if ($actor->can('legislation.manage')) {
            return $actor;
        }

        $session->loadMissing('secretary');
        $secretary = $session->secretary;

        if ($secretary instanceof User && $secretary->can('legislation.manage')) {
            return $secretary;
        }

        $manager = User::query()
            ->permission('legislation.manage')
            ->where('is_active', true)
            ->first();

        return $manager instanceof User ? $manager : null;
    }

    /**
     * @return array{yes: int, no: int, abstain: int, inhibit: int, total: int}
     */
    public function tallies(LegislativeSession $session, AgendaItem $agendaItem, int $votingRound): array
    {
        $votes = Vote::query()
            ->where('session_id', $session->getKey())
            ->where('agenda_item_id', $agendaItem->getKey())
            ->where('voting_round', $votingRound)
            ->get();

        return Vote::tallyLatest($votes);
    }

    public function electronicIsBinding(): bool
    {
        return $this->settings->electronicIsBinding();
    }

    /**
     * The latest closed round on this item that actually recorded a ballot.
     * An open/close with nobody voting does not count.
     */
    public function latestClosedRoundWithBallots(LegislativeSession $session, AgendaItem $agendaItem): ?int
    {
        if ($agendaItem->voting_open_at !== null) {
            return null;
        }

        $round = (int) $agendaItem->voting_round;

        if ($round < 1) {
            return null;
        }

        $hasBallot = Vote::query()
            ->where('session_id', $session->getKey())
            ->where('agenda_item_id', $agendaItem->getKey())
            ->where('voting_round', $round)
            ->exists();

        return $hasBallot ? $round : null;
    }

    private function nextRound(LegislativeSession $session, AgendaItem $agendaItem): int
    {
        $max = Vote::query()
            ->where('session_id', $session->getKey())
            ->where('agenda_item_id', $agendaItem->getKey())
            ->max('voting_round');

        return max(1, ((int) $max) + 1);
    }
}
