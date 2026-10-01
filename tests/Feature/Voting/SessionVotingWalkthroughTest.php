<?php

use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Events\MotionRecorded;
use App\Events\VoteCast;
use App\Events\VotingClosed;
use App\Events\VotingOpened;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use App\Models\Vote;
use App\States\Session\Adjourned;
use App\States\Session\AgendaPrepared;
use App\States\Session\Draft;
use App\States\Session\InSession;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    Event::fake([
        VotingOpened::class,
        VoteCast::class,
        VotingClosed::class,
        MotionRecorded::class,
    ]);
});

function walkthroughActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-walk@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

it('walks through motion, voting, and adjournment over HTTP', function (): void {
    $secretariat = walkthroughActor(UserRole::Secretariat);
    $presiding = walkthroughActor(UserRole::PresidingOfficer);
    $memberA = walkthroughActor(UserRole::BoardMember);
    $memberB = walkthroughActor(UserRole::CommitteeChair);

    $create = $this->actingAs($secretariat)->post(route('sessions.store'), [
        'session_number' => 'WS-200',
        'title' => 'Walkthrough Session',
        'type' => 'regular',
        'legislative_year' => 2026,
        'venue' => 'Session Hall',
        'seated_member_count' => 12,
    ]);
    $create->assertRedirect();

    $session = LegislativeSession::query()->firstOrFail();
    expect($session->status)->toBeInstanceOf(Draft::class);

    $this->actingAs($secretariat)->post(route('sessions.prepare-agenda', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(AgendaPrepared::class);

    $this->actingAs($secretariat)->post(route('sessions.schedule', $session))->assertRedirect();

    $this->actingAs($secretariat)->post(route('sessions.start', $session))->assertRedirect();
    $session = $session->fresh();
    expect($session->status)->toBeInstanceOf(InSession::class)
        ->and($session->agendaItems()->where('status', 'in-progress')->count())->toBe(0);

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    $currentItem = $session->agendaItems()->where('status', 'in-progress')->firstOrFail();

    $this->actingAs($memberA)->post(route('sessions.motions.store', $session), [
        'agenda_item_id' => $currentItem->getKey(),
        'text' => 'I move that the measure be approved.',
        'type' => 'main',
    ])->assertRedirect();

    expect(Motion::query()->count())->toBe(1);

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $currentItem->getKey(),
    ])->assertRedirect();

    $currentItem = $currentItem->fresh();
    expect($currentItem->voting_open_at)->not->toBeNull();

    $round = (int) $currentItem->voting_round;

    $this->actingAs($memberA)->postJson(route('sessions.voting.cast', $session), [
        'agenda_item_id' => $currentItem->getKey(),
        'choice' => VoteChoice::Yes->value,
        'voting_round' => $round,
    ])->assertCreated();

    $this->actingAs($memberB)->postJson(route('sessions.voting.cast', $session), [
        'agenda_item_id' => $currentItem->getKey(),
        'choice' => VoteChoice::No->value,
        'voting_round' => $round,
    ])->assertCreated();

    expect(Vote::query()->count())->toBe(2);

    $this->actingAs($presiding)->post(route('sessions.voting.close', $session), [
        'agenda_item_id' => $currentItem->getKey(),
    ])->assertRedirect();

    expect($currentItem->fresh()->voting_open_at)->toBeNull()
        ->and($currentItem->fresh()->voting_opened_at)->not->toBeNull()
        ->and($currentItem->fresh()->voting_closed_at)->not->toBeNull();

    $this->actingAs($presiding)->post(route('sessions.adjourn', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(Adjourned::class);
});
