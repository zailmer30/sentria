<?php

namespace App\Services\AI;

use App\Contracts\AI\MinutesGenerationService;
use App\DTO\AI\MinutesDiscussionResult;
use App\Enums\AttendanceStatus;
use App\Models\AgendaItem;
use App\Models\AuditLog;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\Motion;
use App\Models\SessionGuest;
use App\Models\User;
use App\Models\Vote;
use App\Services\Audit\AuditLogger;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Minutes\AiDraft;
use App\States\Minutes\SessionCompleted;
use App\States\Session\Adjourned;
use App\States\Session\MinutesForReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class LegislativeMinutesGenerator implements MinutesGenerationService
{
    public const DRAFT_BANNER = 'AI-GENERATED DRAFT — REQUIRES SECRETARIAT REVIEW';

    public const DISPLAY_TIMEZONE = 'Asia/Manila';

    public const TIME_NOT_RECORDED = 'time not recorded';

    public const SECRETARIAT_MINUTES_HEADING = '## Secretariat Minutes';

    public const INVITED_GUESTS_HEADING = '## Invited guests';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly GuardedStateTransition $transitions,
        private readonly AgendaTranscriptSlicer $transcriptSlicer,
        private readonly MinutesDiscussionSummarizer $discussions,
    ) {}

    public function draftFromSession(User $actor, LegislativeSession $session): Minutes
    {
        if (! $actor->can('minutes.generateDraft')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->sessionEligible($session)) {
            throw new InvalidArgumentException('Minutes draft generation requires an adjourned session.');
        }

        $session->loadMissing([
            'agendaItems',
            'agendaItems.minutesCorrections',
            'attendance.user',
            'guests',
            'motions.mover',
            'motions.seconder',
            'votes.agendaItem',
            'secretary',
            'presidingOfficer',
        ]);

        $minutes = Minutes::query()->firstOrCreate(
            ['session_id' => $session->getKey()],
            [
                'status' => SessionCompleted::$name,
                'revision' => 1,
                'prepared_by' => $actor->getKey(),
            ],
        );

        if (! $minutes->allowsDraftGeneration()) {
            throw new InvalidArgumentException('minutes.regenerate_refused');
        }

        $voteWindows = $this->resolveAndBackfillVoteWindows($session);
        $voteTallies = $this->officialVoteTallies($session, $voteWindows);
        $discussion = $this->discussions->summarize(
            $session,
            $this->transcriptSlicer->speechByItem($session),
        );
        $draft = $this->buildStructuredDraft($session, $voteTallies, $voteWindows, $discussion);

        $metadata = [
            'banner' => self::DRAFT_BANNER,
            'source' => 'official-records',
            'requires_human_verification' => true,
            'vote_tallies' => $voteTallies,
            'discussion_summaries_skipped' => $discussion->skipped,
        ];

        if ($discussion->skipReason !== null) {
            $metadata['discussion_summaries_reason'] = $discussion->skipReason;
        }

        if ($discussion->chatModel !== null) {
            $metadata['discussion_chat_model'] = $discussion->chatModel;
        }

        $minutes->update([
            'ai_draft' => $draft,
            'content' => $draft,
            'ai_generated_at' => now(),
            'ai_model' => 'official-records-minutes',
            'ai_metadata' => $metadata,
            'prepared_by' => $minutes->prepared_by ?? $actor->getKey(),
        ]);

        $minutes->refresh();

        if ($minutes->status instanceof SessionCompleted) {
            $this->transitions->transition($minutes, AiDraft::class, $actor);
        }

        $minutes = $minutes->fresh();
        abort_unless($minutes instanceof Minutes, 500, 'Minutes record missing after draft generation.');

        $this->audit->record(
            event: 'ai.minutes.draft',
            category: 'ai',
            auditable: $minutes,
            actor: $actor,
            new: [
                'session_id' => $session->getKey(),
                'status' => $minutes->status->getValue(),
                'vote_tally_count' => count($voteTallies),
                'discussion_summaries_skipped' => $discussion->skipped,
                'discussion_summaries_reason' => $discussion->skipReason,
                'discussion_paragraph_count' => count($discussion->paragraphs),
            ],
            message: 'AI draft minutes generated from official session records.',
        );

        return $minutes;
    }

    private function sessionEligible(LegislativeSession $session): bool
    {
        return $session->status instanceof Adjourned
            || $session->status instanceof MinutesForReview;
    }

    /**
     * @return array<string, array<int, array{opened: Carbon|null, closed: Carbon|null}>>
     */
    private function resolveAndBackfillVoteWindows(LegislativeSession $session): array
    {
        $itemIds = $session->agendaItems->map(fn (AgendaItem $item): string => (string) $item->getKey())->all();
        $windows = [];

        if ($itemIds !== []) {
            $logs = AuditLog::query()
                ->where('auditable_type', (new AgendaItem)->getMorphClass())
                ->whereIn('auditable_id', $itemIds)
                ->whereIn('event', ['voting.opened', 'voting.closed'])
                ->orderBy('occurred_at')
                ->get();

            foreach ($logs as $log) {
                $itemId = (string) $log->auditable_id;
                $values = is_array($log->new_values) ? $log->new_values : [];
                $round = (int) ($values['voting_round'] ?? 0);

                if ($round < 1) {
                    continue;
                }

                if ($log->event === 'voting.opened') {
                    $windows[$itemId][$round] ??= ['opened' => null, 'closed' => null];
                    $windows[$itemId][$round]['opened'] = $log->occurred_at instanceof Carbon ? $log->occurred_at : null;
                } else {
                    $windows[$itemId][$round] ??= ['opened' => null, 'closed' => null];
                    $windows[$itemId][$round]['closed'] = $log->occurred_at instanceof Carbon ? $log->occurred_at : null;
                }
            }
        }

        foreach ($session->agendaItems as $item) {
            $itemId = (string) $item->getKey();
            $round = (int) $item->voting_round;

            if ($round < 1) {
                continue;
            }

            $fromAuditOpened = $windows[$itemId][$round]['opened'] ?? null;
            $fromAuditClosed = $windows[$itemId][$round]['closed'] ?? null;
            $dirty = [];

            if ($item->voting_opened_at === null && $fromAuditOpened instanceof Carbon) {
                $dirty['voting_opened_at'] = $fromAuditOpened;
            }

            if ($item->voting_closed_at === null && $fromAuditClosed instanceof Carbon) {
                $dirty['voting_closed_at'] = $fromAuditClosed;
            }

            if ($dirty !== []) {
                $item->update($dirty);
                $item->refresh();
            }

            $windows[$itemId][$round] = [
                'opened' => $item->voting_opened_at ?? ($fromAuditOpened instanceof Carbon ? $fromAuditOpened : null),
                'closed' => $item->voting_closed_at ?? ($fromAuditClosed instanceof Carbon ? $fromAuditClosed : null),
            ];
        }

        return $windows;
    }

    /**
     * @param  array<string, array<int, array{opened: Carbon|null, closed: Carbon|null}>>  $voteWindows
     * @return list<array{agenda_item_id: string|null, title: string, voting_round: int, yes: int, no: int, abstain: int, inhibit: int, opened_at: string|null, closed_at: string|null}>
     */
    private function officialVoteTallies(LegislativeSession $session, array $voteWindows): array
    {
        $votes = Vote::query()
            ->where('session_id', $session->getKey())
            ->with('agendaItem')
            ->get();

        $grouped = $votes->groupBy(fn (Vote $vote): string => ($vote->agenda_item_id ?? 'none').':'.$vote->voting_round);
        $seen = [];
        $tallies = [];

        foreach ($grouped as $key => $ballots) {
            /** @var Vote $first */
            $first = $ballots->first();
            $parts = explode(':', (string) $key);
            $round = (int) ($parts[1] ?? 1);
            $agendaItem = $first->agendaItem;
            $itemId = $first->agenda_item_id;
            $window = $itemId !== null ? ($voteWindows[(string) $itemId][$round] ?? []) : [];

            $seen[(string) $key] = true;
            $tally = Vote::tallyLatest($ballots);
            $tallies[] = $this->tallyRow(
                agendaItemId: $itemId,
                title: $agendaItem !== null ? $agendaItem->title : 'Unassigned',
                round: $round,
                yes: $tally['yes'],
                no: $tally['no'],
                abstain: $tally['abstain'],
                inhibit: $tally['inhibit'],
                openedAt: $window['opened'] ?? null,
                closedAt: $window['closed'] ?? null,
                position: $agendaItem !== null ? $agendaItem->position : PHP_INT_MAX,
            );
        }

        foreach ($session->agendaItems as $item) {
            $round = (int) $item->voting_round;

            if ($round < 1) {
                continue;
            }

            $key = $item->getKey().':'.$round;

            if (isset($seen[$key])) {
                continue;
            }

            $window = $voteWindows[(string) $item->getKey()][$round] ?? [];

            if (($window['opened'] ?? null) === null && ($window['closed'] ?? null) === null && $item->voting_opened_at === null && $item->voting_closed_at === null) {
                continue;
            }

            $tallies[] = $this->tallyRow(
                agendaItemId: $item->getKey(),
                title: $item->title,
                round: $round,
                yes: 0,
                no: 0,
                abstain: 0,
                inhibit: 0,
                openedAt: $window['opened'] ?? $item->voting_opened_at,
                closedAt: $window['closed'] ?? $item->voting_closed_at,
                position: $item->position,
            );
        }

        usort($tallies, function (array $a, array $b): int {
            $position = ($a['_position'] ?? 0) <=> ($b['_position'] ?? 0);

            if ($position !== 0) {
                return $position;
            }

            return $a['voting_round'] <=> $b['voting_round'];
        });

        return array_map(function (array $tally): array {
            unset($tally['_position']);

            return $tally;
        }, $tallies);
    }

    /**
     * @return array{agenda_item_id: string|null, title: string, voting_round: int, yes: int, no: int, abstain: int, inhibit: int, opened_at: string|null, closed_at: string|null, _position: int}
     */
    private function tallyRow(
        ?string $agendaItemId,
        string $title,
        int $round,
        int $yes,
        int $no,
        int $abstain,
        int $inhibit,
        ?Carbon $openedAt,
        ?Carbon $closedAt,
        int $position,
    ): array {
        return [
            'agenda_item_id' => $agendaItemId,
            'title' => $title,
            'voting_round' => $round,
            'yes' => $yes,
            'no' => $no,
            'abstain' => $abstain,
            'inhibit' => $inhibit,
            'opened_at' => $openedAt?->toIso8601String(),
            'closed_at' => $closedAt?->toIso8601String(),
            '_position' => $position,
        ];
    }

    /**
     * @param  list<array{agenda_item_id: string|null, title: string, voting_round: int, yes: int, no: int, abstain: int, inhibit: int, opened_at: string|null, closed_at: string|null}>  $voteTallies
     * @param  array<string, array<int, array{opened: Carbon|null, closed: Carbon|null}>>  $voteWindows
     */
    private function buildStructuredDraft(
        LegislativeSession $session,
        array $voteTallies,
        array $voteWindows,
        MinutesDiscussionResult $discussion,
    ): string {
        $lines = [
            '# Minutes — '.$session->title,
            '',
            '> '.self::DRAFT_BANNER,
            '',
            '## Session Information',
            '- Session number: '.$session->session_number,
            '- Date: '.$this->calendarDate($session->adjourned_at ?? $session->actual_start_at ?? $session->scheduled_start_at),
            '- Venue: '.($session->venue ?? 'N/A'),
            '- Presiding Officer: '.($session->presidingOfficer !== null ? $session->presidingOfficer->display_name : 'N/A'),
            '- Secretary: '.($session->secretary !== null ? $session->secretary->display_name : 'N/A'),
            '- Actual start: '.$this->clock($session->actual_start_at),
            '- Adjournment: '.$this->clock($session->adjourned_at ?? $session->actual_end_at),
            '',
            '## Attendance',
        ];

        foreach ($session->attendance as $record) {
            $memberName = $record->user !== null ? $record->user->display_name : 'Member';
            $status = (string) $record->status;
            $line = sprintf('- %s — %s', $memberName, $status);

            if (in_array($status, [AttendanceStatus::Present->value, AttendanceStatus::Late->value], true)) {
                $line .= ', checked in '.$this->clock($record->checked_in_at);
            } else {
                $remarks = trim((string) $record->remarks);

                if ($remarks !== '') {
                    $line .= ' ('.$remarks.')';
                }
            }

            $lines[] = $line;
        }

        if ($session->attendance->isEmpty()) {
            $lines[] = '- No attendance records.';
        }

        $guestLines = $session->guests
            ->map(fn (SessionGuest $guest): string => $guest->minutesLine())
            ->all();

        if ($guestLines !== []) {
            $lines[] = '';
            $lines[] = self::INVITED_GUESTS_HEADING;
            array_push($lines, ...$guestLines);
        }

        $lines[] = '';
        $lines[] = '## Proceedings';
        $lines = array_merge($lines, $this->proceedingsLines($session, $voteTallies, $voteWindows, $discussion->paragraphs));

        $lines[] = '';
        $lines[] = '## Motions on Record';
        $lines = array_merge($lines, $this->motionAnnexLines($session->motions));

        $lines[] = '';
        $lines[] = '## Official Vote Results (authoritative)';
        $lines = array_merge($lines, $this->voteAnnexLines($voteTallies));

        $pending = $session->agendaItems
            ->where('status', '!=', 'completed')
            ->values();

        if ($pending->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Pending matters';

            foreach ($pending as $item) {
                $lines[] = sprintf(
                    '- %s. %s (%s)',
                    $item->item_number ?? '-',
                    $item->title,
                    $item->status,
                );
            }
        }

        if ($session->notes !== null && trim($session->notes) !== '') {
            $lines[] = '';
            $lines[] = '## Secretariat Notes';
            $lines[] = trim($session->notes);
        }

        return $this->applySecretariatMinutesToDraft(implode("\n", $lines), $session->secretariat_minutes);
    }

    /**
     * Fold (or remove) the clerk-typed minutes into a generated draft. The
     * section is always last so a later save can replace it without touching
     * the official-record body above it.
     */
    public function applySecretariatMinutesToDraft(string $draft, ?string $manual): string
    {
        $stripped = preg_replace(
            '/(?:\R)*'.preg_quote(self::SECRETARIAT_MINUTES_HEADING, '/').'\R.*\z/s',
            '',
            $draft,
        );

        if (! is_string($stripped)) {
            $stripped = $draft;
        }

        $manual = trim((string) $manual);

        if ($manual === '') {
            return rtrim($stripped);
        }

        return rtrim($stripped)."\n\n".self::SECRETARIAT_MINUTES_HEADING."\n".$manual;
    }

    /**
     * @param  list<array{agenda_item_id: string|null, title: string, voting_round: int, yes: int, no: int, abstain: int, inhibit: int, opened_at: string|null, closed_at: string|null}>  $voteTallies
     * @param  array<string, array<int, array{opened: Carbon|null, closed: Carbon|null}>>  $voteWindows
     * @param  array<string, string>  $discussionParagraphs
     * @return list<string>
     */
    private function proceedingsLines(
        LegislativeSession $session,
        array $voteTallies,
        array $voteWindows,
        array $discussionParagraphs,
    ): array {
        $lines = [];
        $motionsByItem = $session->motions->groupBy(fn (Motion $motion): string => (string) ($motion->agenda_item_id ?? 'none'));
        $tallyRoundsByItem = [];

        foreach ($voteTallies as $tally) {
            $key = $tally['agenda_item_id'] ?? 'none';
            $tallyRoundsByItem[$key][] = $tally['voting_round'];
        }

        if ($session->agendaItems->isEmpty()) {
            $lines[] = '- No agenda items.';
        }

        foreach ($session->agendaItems as $item) {
            $itemId = (string) $item->getKey();
            $end = $item->completed_at;

            if ($end === null && $item->status === 'in-progress') {
                $end = $session->adjourned_at ?? $session->actual_end_at;
            }

            $lines[] = sprintf(
                '- %s %s. %s',
                $this->span($item->started_at, $end),
                $item->item_number ?? '-',
                $item->title,
            );

            $discussion = $discussionParagraphs[$itemId] ?? null;

            if (is_string($discussion) && $discussion !== '') {
                $lines[] = '  - '.$discussion;
            }

            if ($item->category === 'roll-call') {
                if ($session->quorum_declared_at !== null) {
                    $lines[] = '  - Quorum declared '.$this->clock($session->quorum_declared_at);
                } else {
                    $lines[] = '  - Quorum not declared';
                }
            }

            if ($item->category === 'approval-minutes' && $item->document_id !== null) {
                $lines[] = '  - Considered the minutes: '.$item->title;

                $corrections = $item->minutesCorrections ?? collect();

                foreach ($corrections as $correction) {
                    $page = $correction->page_number !== null ? ' p.'.$correction->page_number : '';
                    $lines[] = '  - Correction'.$page.': "'.$correction->as_written.'" should read "'.$correction->should_read.'"';
                }
            }

            /** @var Collection<int, Motion> $motions */
            $motions = $motionsByItem->get($itemId, collect());

            foreach ($motions as $motion) {
                $lines[] = '  - '.$this->clock($motion->moved_at).' Motion moved — see Motions on Record';

                if ($motion->seconded_at !== null) {
                    $lines[] = '  - '.$this->clock($motion->seconded_at).' Motion seconded — see Motions on Record';
                }
            }

            $rounds = $tallyRoundsByItem[$itemId] ?? array_keys($voteWindows[$itemId] ?? []);
            $rounds = array_values(array_unique(array_map('intval', $rounds)));
            sort($rounds);

            foreach ($rounds as $round) {
                if ($round < 1) {
                    continue;
                }

                $window = $voteWindows[$itemId][$round] ?? [];
                $opened = $window['opened'] ?? ($item->voting_round === $round ? $item->voting_opened_at : null);
                $closed = $window['closed'] ?? ($item->voting_round === $round ? $item->voting_closed_at : null);
                $roundLabel = ' (Round '.$round.')';

                $lines[] = '  - '.$this->clock($opened).' Vote opened'.$roundLabel.' — see Official Vote Results';
                $lines[] = '  - '.$this->clock($closed).' Vote closed'.$roundLabel.' — see Official Vote Results';
            }
        }

        $unassignedMotions = $motionsByItem->get('none', collect());
        $unassignedRounds = $tallyRoundsByItem['none'] ?? [];

        if ($unassignedMotions->isNotEmpty() || $unassignedRounds !== []) {
            $lines[] = '- '.self::TIME_NOT_RECORDED.' Unassigned';

            foreach ($unassignedMotions as $motion) {
                $lines[] = '  - '.$this->clock($motion->moved_at).' Motion moved — see Motions on Record';

                if ($motion->seconded_at !== null) {
                    $lines[] = '  - '.$this->clock($motion->seconded_at).' Motion seconded — see Motions on Record';
                }
            }

            foreach (array_values(array_unique($unassignedRounds)) as $round) {
                $lines[] = '  - '.self::TIME_NOT_RECORDED.' Vote opened (Round '.$round.') — see Official Vote Results';
                $lines[] = '  - '.self::TIME_NOT_RECORDED.' Vote closed (Round '.$round.') — see Official Vote Results';
            }
        }

        return $lines;
    }

    /**
     * @param  Collection<int, Motion>  $motions
     * @return list<string>
     */
    private function motionAnnexLines(Collection $motions): array
    {
        if ($motions->isEmpty()) {
            return ['- None recorded.'];
        }

        $lines = [];

        foreach ($motions as $motion) {
            $moverName = $motion->mover !== null ? $motion->mover->display_name : 'unknown';
            $parts = [
                $this->clock($motion->moved_at).' '.$motion->text,
                'moved by '.$moverName,
            ];

            if ($motion->seconded_at !== null) {
                $seconderName = $motion->seconder !== null ? $motion->seconder->display_name : 'unknown';
                $parts[] = 'seconded by '.$seconderName.' at '.$this->clock($motion->seconded_at);
            } else {
                $parts[] = 'not seconded';
            }

            $parts[] = str_replace('_', ' ', (string) $motion->status);

            if ($motion->disposed_at !== null) {
                $parts[] = 'disposed '.$this->clock($motion->disposed_at);
            }

            $lines[] = '- '.$parts[0].' ('.implode('; ', array_slice($parts, 1)).')';
        }

        return $lines;
    }

    /**
     * @param  list<array{agenda_item_id: string|null, title: string, voting_round: int, yes: int, no: int, abstain: int, inhibit: int, opened_at: string|null, closed_at: string|null}>  $voteTallies
     * @return list<string>
     */
    private function voteAnnexLines(array $voteTallies): array
    {
        if ($voteTallies === []) {
            return ['- No official electronic votes recorded.'];
        }

        $lines = [];

        foreach ($voteTallies as $tally) {
            $lines[] = sprintf('### %s (Round %d)', $tally['title'], $tally['voting_round']);
            $lines[] = 'Vote opened: '.$this->clockIso($tally['opened_at']);
            $lines[] = 'Vote closed: '.$this->clockIso($tally['closed_at']);
            $lines[] = sprintf(
                'YES: %d NO: %d ABSTAIN: %d INHIBIT: %d',
                $tally['yes'],
                $tally['no'],
                $tally['abstain'],
                $tally['inhibit'],
            );
            $lines[] = '';
        }

        return $lines;
    }

    private function clock(?Carbon $at): string
    {
        if ($at === null) {
            return self::TIME_NOT_RECORDED;
        }

        return $at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('H:i');
    }

    private function clockIso(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return self::TIME_NOT_RECORDED;
        }

        return $this->clock(Carbon::parse($iso));
    }

    private function calendarDate(?Carbon $at): string
    {
        if ($at === null) {
            return 'N/A';
        }

        return $at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->toDateString();
    }

    private function span(?Carbon $start, ?Carbon $end): string
    {
        if ($start === null && $end === null) {
            return self::TIME_NOT_RECORDED;
        }

        return $this->clock($start).'–'.$this->clock($end);
    }
}
