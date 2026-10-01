<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\SessionAttendance;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Vote;
use App\Services\AI\LegislativeMinutesGenerator;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    config(['sentria.ai.api_key' => null]);
});

function preciseDraftActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-precise-draft-'.fake()->unique()->numerify('####').'@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('builds a timed proceedings draft from official records only', function (): void {
    $secretariat = preciseDraftActor();
    $presiding = User::factory()->create([
        'display_name' => 'Hon. Presiding',
        'email' => 'po-precise-draft@sentria.test',
        'is_active' => true,
    ]);
    $member = User::factory()->create([
        'display_name' => 'Corazon Manalo',
        'email' => 'member-precise-draft@sentria.test',
        'is_active' => true,
    ]);
    $absent = User::factory()->create([
        'display_name' => 'Danilo Salazar',
        'email' => 'absent-precise-draft@sentria.test',
        'is_active' => true,
    ]);

    $session = LegislativeSession::factory()->adjourned()->create([
        'title' => '1st Regular Session',
        'session_number' => 'RS-2026-01',
        'secretary_id' => $secretariat->getKey(),
        'presiding_officer_id' => $presiding->getKey(),
        'venue' => 'Session Hall',
        'actual_start_at' => Carbon::parse('2026-08-31 09:00:00', 'Asia/Manila'),
        'actual_end_at' => Carbon::parse('2026-08-31 12:30:00', 'Asia/Manila'),
        'adjourned_at' => Carbon::parse('2026-08-31 12:30:00', 'Asia/Manila'),
        'quorum_declared_at' => Carbon::parse('2026-08-31 09:06:00', 'Asia/Manila'),
        'notes' => 'Verify citations before final.',
        'secretariat_minutes' => 'The chair reminded members to speak through the chair.',
    ]);

    $rollCall = AgendaItem::factory()->procedural('roll-call', 'Roll Call', 4)->create([
        'session_id' => $session->getKey(),
        'status' => 'completed',
        'started_at' => Carbon::parse('2026-08-31 09:04:00', 'Asia/Manila'),
        'completed_at' => Carbon::parse('2026-08-31 09:10:00', 'Asia/Manila'),
    ]);

    $firstReading = AgendaItem::factory()->create([
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

    $pending = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'position' => 8,
        'item_number' => '8',
        'title' => 'Committee Hour',
        'category' => 'committee-report',
        'status' => 'in-progress',
        'started_at' => Carbon::parse('2026-08-31 09:32:00', 'Asia/Manila'),
        'completed_at' => null,
    ]);

    $untimed = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'position' => 12,
        'item_number' => '12',
        'title' => 'Adjournment',
        'category' => 'adjournment',
        'status' => 'completed',
        'started_at' => null,
        'completed_at' => null,
    ]);

    SessionAttendance::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'status' => AttendanceStatus::Late->value,
        'checked_in_at' => Carbon::parse('2026-08-31 09:14:00', 'Asia/Manila'),
    ]);

    SessionAttendance::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $absent->getKey(),
        'status' => AttendanceStatus::Absent->value,
        'checked_in_at' => null,
        'remarks' => 'in hospital',
    ]);

    $seconder = User::factory()->create([
        'display_name' => 'Rafael Dizon',
        'email' => 'seconder-precise-draft@sentria.test',
        'is_active' => true,
    ]);

    Motion::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $firstReading->getKey(),
        'text' => 'I move that the ordinance be approved.',
        'status' => 'carried',
        'moved_by' => $member->getKey(),
        'moved_at' => Carbon::parse('2026-08-31 09:20:00', 'Asia/Manila'),
        'seconded_by' => $seconder->getKey(),
        'seconded_at' => Carbon::parse('2026-08-31 09:21:00', 'Asia/Manila'),
        'disposed_at' => Carbon::parse('2026-08-31 09:28:00', 'Asia/Manila'),
        'voting_round' => 1,
    ]);

    Vote::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $firstReading->getKey(),
        'choice' => VoteChoice::Yes->value,
        'voting_round' => 1,
    ]);
    Vote::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $firstReading->getKey(),
        'choice' => VoteChoice::Inhibit->value,
        'voting_round' => 1,
    ]);

    Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'full_text' => 'I move that this invented motion be adopted. YES: 99 NO: 0 ABSTAIN: 0.',
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session->fresh());
    $content = (string) $minutes->content;

    expect($content)->toContain(LegislativeMinutesGenerator::DRAFT_BANNER)
        ->and($content)->toContain('- Actual start: 09:00')
        ->and($content)->toContain('- Adjournment: 12:30')
        ->and($content)->toContain('- Date: 2026-08-31')
        ->and($content)->toContain('Corazon Manalo — late, checked in 09:14')
        ->and($content)->toContain('Danilo Salazar — absent (in hospital)')
        ->and($content)->not->toContain('Danilo Salazar — absent, checked in')
        ->and($content)->not->toContain('absent ()')
        ->and($content)->toContain('## Proceedings')
        ->and($content)->toContain('- 09:04–09:10 4. Roll Call')
        ->and($content)->toContain('Quorum declared 09:06')
        ->and($content)->toContain('- 09:18–09:31 7. Sample Ordinance')
        ->and($content)->toContain('09:20 Motion moved — see Motions on Record')
        ->and($content)->toContain('09:21 Motion seconded — see Motions on Record')
        ->and($content)->toContain('09:25 Vote opened (Round 1) — see Official Vote Results')
        ->and($content)->toContain('09:28 Vote closed (Round 1) — see Official Vote Results')
        ->and($content)->toContain('- 09:32–12:30 8. Committee Hour')
        ->and($content)->toContain('- time not recorded 12. Adjournment')
        ->and($content)->toContain('## Motions on Record')
        ->and($content)->toContain('I move that the ordinance be approved.')
        ->and($content)->toContain('YES: 1 NO: 0 ABSTAIN: 0 INHIBIT: 1')
        ->and($content)->toContain('## Pending matters')
        ->and($content)->toContain('8. Committee Hour (in-progress)')
        ->and($content)->toContain('## Secretariat Notes')
        ->and($content)->toContain('Verify citations before final.')
        ->and($content)->toContain(LegislativeMinutesGenerator::SECRETARIAT_MINUTES_HEADING)
        ->and($content)->toContain('The chair reminded members to speak through the chair.')
        ->and($content)->not->toContain('## Agenda')
        ->and($content)->not->toContain('## Session Summary')
        ->and($content)->not->toContain('## Transcript Excerpt')
        ->and($content)->not->toContain('[AI Suggested]')
        ->and($content)->not->toContain('YES: 99')
        ->and($content)->not->toContain('invented motion');

    expect($content)->not->toMatch('/'.$member->display_name.' — yes/i');

    expect($rollCall->title)->toBe('Roll Call')
        ->and($pending->status)->toBe('in-progress')
        ->and($untimed->started_at)->toBeNull();
});

it('clocks a vote with no motion and does not invent motion text', function (): void {
    $secretariat = preciseDraftActor();

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
        'actual_start_at' => Carbon::parse('2026-08-31 09:00:00', 'Asia/Manila'),
        'adjourned_at' => Carbon::parse('2026-08-31 11:00:00', 'Asia/Manila'),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'title' => 'Budget Ordinance',
        'item_number' => '7',
        'status' => 'completed',
        'started_at' => Carbon::parse('2026-08-31 09:18:00', 'Asia/Manila'),
        'completed_at' => Carbon::parse('2026-08-31 09:40:00', 'Asia/Manila'),
        'voting_round' => 1,
        'voting_opened_at' => Carbon::parse('2026-08-31 09:30:00', 'Asia/Manila'),
        'voting_closed_at' => Carbon::parse('2026-08-31 09:35:00', 'Asia/Manila'),
    ]);

    Vote::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'choice' => VoteChoice::Yes->value,
        'voting_round' => 1,
    ]);

    $content = (string) app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session->fresh())->content;

    expect($content)->toContain('09:30 Vote opened (Round 1) — see Official Vote Results')
        ->and($content)->toContain('## Motions on Record')
        ->and($content)->toContain('- None recorded.')
        ->and($content)->not->toContain('Vote on Budget Ordinance');
});
