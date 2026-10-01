<?php

use App\Contracts\AI\ChatCompletionService;
use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Vote;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\AI\MinutesDiscussionSummarizer;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\RecordingDiscussionChat;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    config(['sentria.ai.api_key' => null]);
});

function discussionDraftActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-discussion-'.fake()->unique()->numerify('####').'@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

/**
 * @return array{0: User, 1: LegislativeSession, 2: AgendaItem}
 */
function discussionSitting(): array
{
    $secretariat = discussionDraftActor();
    $start = Carbon::parse('2026-08-31 09:00:00', 'Asia/Manila');

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
        'actual_start_at' => $start,
        'actual_end_at' => Carbon::parse('2026-08-31 12:30:00', 'Asia/Manila'),
        'adjourned_at' => Carbon::parse('2026-08-31 12:30:00', 'Asia/Manila'),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'position' => 7,
        'item_number' => '7',
        'title' => 'Sample Ordinance',
        'category' => 'first-reading',
        'status' => 'completed',
        'started_at' => Carbon::parse('2026-08-31 09:18:00', 'Asia/Manila'),
        'completed_at' => Carbon::parse('2026-08-31 09:31:00', 'Asia/Manila'),
        'requires_vote' => true,
        'voting_round' => 1,
        'voting_opened_at' => Carbon::parse('2026-08-31 09:25:00', 'Asia/Manila'),
        'voting_closed_at' => Carbon::parse('2026-08-31 09:28:00', 'Asia/Manila'),
    ]);

    Vote::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'choice' => VoteChoice::Yes->value,
        'voting_round' => 1,
    ]);

    return [$secretariat, $session->fresh(['agendaItems']), $item];
}

function offsetFromSittingStart(LegislativeSession $session, string $clock): float
{
    $epoch = $session->actual_start_at;
    $at = Carbon::parse('2026-08-31 '.$clock, 'Asia/Manila');

    return (float) ($at->getTimestamp() - $epoch->getTimestamp());
}

/**
 * @param  list<array<string, mixed>>  $segments
 */
function chamberTranscript(LegislativeSession $session, array $segments): Transcript
{
    return Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'source' => 'chamber_channels',
        'agenda_item_id' => null,
        'full_text' => collect($segments)->pluck('text')->implode(' '),
        'segments' => $segments,
    ]);
}

function bindDiscussionChat(RecordingDiscussionChat $chat): RecordingDiscussionChat
{
    config(['sentria.ai.api_key' => 'test-key']);
    app()->instance(ChatCompletionService::class, $chat);

    return $chat;
}

it('writes an AI-suggested discussion paragraph from chamber speech in the item window', function (): void {
    [$secretariat, $session, $item] = discussionSitting();
    $start = offsetFromSittingStart($session, '09:20:00');

    chamberTranscript($session, [
        [
            'index' => 1,
            'start' => $start,
            'end' => $start + 20,
            'text' => 'We should increase the allocation for flood control in the coastal barangays.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.92,
        ],
        [
            'index' => 2,
            'start' => $start + 25,
            'end' => $start + 40,
            'text' => 'I move that this invented motion be adopted. YES: 99 NO: 0 ABSTAIN: 0.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.91,
        ],
    ]);

    $chat = bindDiscussionChat(new RecordingDiscussionChat(
        'Members asked about flood control funding in coastal barangays.',
        'gpt-4o-mini',
    ));

    $content = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']))
        ->content;

    expect($content)->toContain('  - '.MinutesDiscussionSummarizer::PREFIX.' Members asked about flood control funding in coastal barangays.')
        ->and($content)->toContain('YES: 1 NO: 0 ABSTAIN: 0 INHIBIT: 0')
        ->and($content)->not->toContain('YES: 99')
        ->and($content)->not->toContain('invented motion')
        ->and($chat->userMessages[0] ?? '')->toContain('Corazon Manalo:')
        ->and($chat->userMessages[0] ?? '')->toContain('7. Sample Ordinance');

    $minutes = $session->fresh()->minutes;
    expect($minutes?->ai_model)->toBe('official-records-minutes')
        ->and($minutes?->ai_metadata['discussion_summaries_skipped'] ?? true)->toBeFalse()
        ->and($minutes?->ai_metadata['discussion_chat_model'] ?? null)->toBe('gpt-4o-mini');

    expect($item->title)->toBe('Sample Ordinance');
});

it('omits a low-confidence segment and an item with no start time', function (): void {
    $secretariat = discussionDraftActor();
    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
        'actual_start_at' => Carbon::parse('2026-08-31 09:00:00', 'Asia/Manila'),
        'adjourned_at' => Carbon::parse('2026-08-31 12:30:00', 'Asia/Manila'),
    ]);

    $timed = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'position' => 7,
        'item_number' => '7',
        'title' => 'Sample Ordinance',
        'status' => 'completed',
        'started_at' => Carbon::parse('2026-08-31 09:18:00', 'Asia/Manila'),
        'completed_at' => Carbon::parse('2026-08-31 09:31:00', 'Asia/Manila'),
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'position' => 12,
        'item_number' => '12',
        'title' => 'Adjournment',
        'category' => 'adjournment',
        'status' => 'completed',
        'started_at' => null,
        'completed_at' => null,
    ]);

    $inWindow = offsetFromSittingStart($session, '09:20:00');

    chamberTranscript($session, [
        [
            'index' => 1,
            'start' => $inWindow,
            'end' => $inWindow + 10,
            'text' => 'This line is too uncertain to quote.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.21,
        ],
        [
            'index' => 2,
            'start' => $inWindow + 4000,
            'end' => $inWindow + 4010,
            'text' => 'Speech with no matching item start still must not invent adjournment discussion.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.95,
        ],
    ]);

    $chat = bindDiscussionChat(new RecordingDiscussionChat('Should never be used.'));

    $content = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']))
        ->content;

    expect($content)->not->toContain(MinutesDiscussionSummarizer::PREFIX)
        ->and($content)->not->toContain('too uncertain')
        ->and($content)->not->toContain('Should never be used')
        ->and($chat->userMessages)->toBe([])
        ->and($timed->item_number)->toBe('7');
});

it('keeps a segment with no confidence score', function (): void {
    [$secretariat, $session] = discussionSitting();
    $start = offsetFromSittingStart($session, '09:20:00');

    chamberTranscript($session, [
        [
            'index' => 1,
            'start' => $start,
            'end' => $start + 12,
            'text' => 'The drainage maps need another survey before second reading.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
        ],
    ]);

    bindDiscussionChat(new RecordingDiscussionChat('A member asked for another drainage survey.'));

    $content = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']))
        ->content;

    expect($content)->toContain(MinutesDiscussionSummarizer::PREFIX.' A member asked for another drainage survey.');
});

it('uses a tagged upload only under that item when there is no chamber transcript', function (): void {
    [$secretariat, $session, $item] = discussionSitting();

    Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'source' => 'live_stt',
        'agenda_item_id' => $item->getKey(),
        'full_text' => 'The committee asked whether the setback applies to existing homes.',
        'segments' => [
            [
                'index' => 1,
                'start' => 0,
                'end' => 12,
                'text' => 'The committee asked whether the setback applies to existing homes.',
                'speaker' => null,
                'attributed' => false,
                'confidence' => 0.88,
            ],
        ],
    ]);

    $chat = bindDiscussionChat(new RecordingDiscussionChat('The committee asked whether the setback covers existing homes.'));

    $content = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']))
        ->content;

    expect($content)->toContain(MinutesDiscussionSummarizer::PREFIX.' The committee asked whether the setback covers existing homes.')
        ->and($chat->userMessages[0] ?? '')->toContain('Unassigned:')
        ->and($chat->userMessages[0] ?? '')->not->toContain('Hon. Fake');
});

it('ignores an untagged upload and ignores uploads when a chamber transcript exists', function (): void {
    [$secretariat, $session, $item] = discussionSitting();

    Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'source' => 'live_stt',
        'agenda_item_id' => null,
        'full_text' => 'Untagged upload must not appear in the minutes.',
        'segments' => [
            [
                'index' => 1,
                'start' => 0,
                'end' => 8,
                'text' => 'Untagged upload must not appear in the minutes.',
                'speaker' => 'Hon. Fake',
                'attributed' => true,
                'confidence' => 0.99,
            ],
        ],
    ]);

    $chat = bindDiscussionChat(new RecordingDiscussionChat('Should never be used.'));

    $content = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']))
        ->content;

    expect($content)->not->toContain(MinutesDiscussionSummarizer::PREFIX)
        ->and($chat->userMessages)->toBe([]);

    chamberTranscript($session, [
        [
            'index' => 1,
            'start' => offsetFromSittingStart($session, '09:20:00'),
            'end' => offsetFromSittingStart($session, '09:20:10'),
            'text' => 'Chamber audio is the source when both exist.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.9,
        ],
    ]);

    Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'source' => 'live_stt',
        'agenda_item_id' => $item->getKey(),
        'full_text' => 'Tagged upload must be ignored when chamber audio exists.',
        'segments' => [
            [
                'index' => 1,
                'start' => 0,
                'end' => 8,
                'text' => 'Tagged upload must be ignored when chamber audio exists.',
                'speaker' => 'Hon. Fake',
                'attributed' => true,
                'confidence' => 0.99,
            ],
        ],
    ]);

    $chat = bindDiscussionChat(new RecordingDiscussionChat('The chamber recording was used.'));

    $content = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']))
        ->content;

    expect($content)->toContain(MinutesDiscussionSummarizer::PREFIX.' The chamber recording was used.')
        ->and($chat->userMessages[0] ?? '')->toContain('Chamber audio is the source')
        ->and($chat->userMessages[0] ?? '')->not->toContain('Tagged upload must be ignored');
});

it('closes an item window at the next item start when completed_at is missing', function (): void {
    $secretariat = discussionDraftActor();
    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
        'actual_start_at' => Carbon::parse('2026-08-31 09:00:00', 'Asia/Manila'),
        'adjourned_at' => Carbon::parse('2026-08-31 12:30:00', 'Asia/Manila'),
    ]);

    $first = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'position' => 7,
        'item_number' => '7',
        'title' => 'Sample Ordinance',
        'status' => 'in-progress',
        'started_at' => Carbon::parse('2026-08-31 09:18:00', 'Asia/Manila'),
        'completed_at' => null,
    ]);

    $second = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'position' => 8,
        'item_number' => '8',
        'title' => 'Committee Hour',
        'status' => 'in-progress',
        'started_at' => Carbon::parse('2026-08-31 09:32:00', 'Asia/Manila'),
        'completed_at' => null,
    ]);

    chamberTranscript($session, [
        [
            'index' => 1,
            'start' => offsetFromSittingStart($session, '09:20:00'),
            'end' => offsetFromSittingStart($session, '09:20:20'),
            'text' => 'First item drainage questions.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.9,
        ],
        [
            'index' => 2,
            'start' => offsetFromSittingStart($session, '09:32:00'),
            'end' => offsetFromSittingStart($session, '09:32:20'),
            'text' => 'Committee hour staffing questions.',
            'speaker' => 'Rafael Dizon',
            'attributed' => true,
            'confidence' => 0.9,
        ],
    ]);

    $chat = bindDiscussionChat(new RecordingDiscussionChat('Discussion of the open item.'));

    $content = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']))
        ->content;

    expect($chat->userMessages)->toHaveCount(2)
        ->and($chat->userMessages[0])->toContain('First item drainage questions')
        ->and($chat->userMessages[0])->not->toContain('Committee hour staffing')
        ->and($chat->userMessages[1])->toContain('Committee hour staffing questions')
        ->and($chat->userMessages[1])->not->toContain('First item drainage')
        ->and($content)->toContain('7. Sample Ordinance')
        ->and($content)->toContain('8. Committee Hour')
        ->and($first->item_number)->toBe('7')
        ->and($second->item_number)->toBe('8');
});

it('uses the current segment text after a secretariat correction', function (): void {
    [$secretariat, $session] = discussionSitting();
    $start = offsetFromSittingStart($session, '09:20:00');

    chamberTranscript($session, [
        [
            'index' => 1,
            'start' => $start,
            'end' => $start + 12,
            'text' => 'The corrected line about the shoreline setback.',
            'original_text' => 'The machine heard something else entirely.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.9,
        ],
    ]);

    $chat = bindDiscussionChat(new RecordingDiscussionChat('Members discussed the shoreline setback.'));

    app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']));

    expect($chat->userMessages[0] ?? '')->toContain('The corrected line about the shoreline setback.')
        ->and($chat->userMessages[0] ?? '')->not->toContain('The machine heard something else entirely.');
});

it('still generates the official-record draft when chat is unavailable', function (): void {
    [$secretariat, $session] = discussionSitting();
    $start = offsetFromSittingStart($session, '09:20:00');

    chamberTranscript($session, [
        [
            'index' => 1,
            'start' => $start,
            'end' => $start + 12,
            'text' => 'Members asked about drainage maps.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.9,
        ],
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']));
    $content = (string) $minutes->content;

    expect($content)->toContain(LegislativeMinutesGenerator::DRAFT_BANNER)
        ->and($content)->toContain('YES: 1 NO: 0 ABSTAIN: 0 INHIBIT: 0')
        ->and($content)->not->toContain(MinutesDiscussionSummarizer::PREFIX)
        ->and($minutes->ai_metadata['discussion_summaries_skipped'] ?? false)->toBeTrue()
        ->and($minutes->ai_metadata['discussion_summaries_reason'] ?? null)->toBe('chat_unavailable');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'ai.minutes.draft',
        'auditable_id' => $minutes->getKey(),
    ]);
});

it('omits discussion paragraphs when chat fails and still writes the draft', function (): void {
    [$secretariat, $session] = discussionSitting();
    $start = offsetFromSittingStart($session, '09:20:00');

    chamberTranscript($session, [
        [
            'index' => 1,
            'start' => $start,
            'end' => $start + 12,
            'text' => 'Members asked about drainage maps.',
            'speaker' => 'Corazon Manalo',
            'attributed' => true,
            'confidence' => 0.9,
        ],
    ]);

    bindDiscussionChat(new RecordingDiscussionChat(
        'unused',
        'gpt-4o-mini',
        new RuntimeException('provider down'),
    ));

    $minutes = app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh(['agendaItems']));

    expect((string) $minutes->content)->toContain(LegislativeMinutesGenerator::DRAFT_BANNER)
        ->and((string) $minutes->content)->not->toContain(MinutesDiscussionSummarizer::PREFIX)
        ->and($minutes->ai_metadata['discussion_summaries_skipped'] ?? false)->toBeTrue()
        ->and($minutes->ai_metadata['discussion_summaries_reason'] ?? null)->toBe('chat_failed');
});
