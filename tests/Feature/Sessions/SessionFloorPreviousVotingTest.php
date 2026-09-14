<?php

use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Events\HallDisplayChanged;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;
use App\Models\Vote;
use App\States\Session\InSession;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

function previousVotingActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-prev-vote-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $role !== UserRole::Secretariat,
        'display_name' => "Hon. {$suffix}",
    ])->assignRole($role->value);
}

/**
 * @return array{secretariat: User, member: User, pending: User, session: LegislativeSession, item: AgendaItem}
 */
function sittingWithClosedVote(string $suffix = 'closed'): array
{
    $secretariat = previousVotingActor(UserRole::Secretariat, "sec-{$suffix}");
    $member = previousVotingActor(UserRole::BoardMember, "Reyes-{$suffix}");
    $pending = previousVotingActor(UserRole::BoardMember, "Santos-{$suffix}");

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
        'seated_member_count' => 12,
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'requires_vote' => true,
        'voting_round' => 1,
        'voting_open_at' => null,
        'voting_opened_at' => now()->subMinutes(5),
        'voting_closed_at' => now()->subMinute(),
        'title' => 'Measure on the floor',
    ]);

    SessionAttendance::factory()->present()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
    ]);
    SessionAttendance::factory()->present()->create([
        'session_id' => $session->getKey(),
        'user_id' => $pending->getKey(),
    ]);

    Vote::factory()->choice(VoteChoice::Yes)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $member->getKey(),
        'voting_round' => 1,
    ]);

    return compact('secretariat', 'member', 'pending', 'session', 'item');
}

it('exposes the latest closed roll on the floor payload after voting closes', function (): void {
    ['secretariat' => $secretariat, 'member' => $member, 'pending' => $pending, 'session' => $session] = sittingWithClosedVote('payload');

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Dashboard')
            ->where('voting.open', false)
            ->where('voting.previous.round', 1)
            ->where('voting.previous.tallies.yes', 1)
            ->where('voting.previous.tallies.total', 1)
            ->has('voting.previous.members', 2)
            ->where('voting.previous.members.0.has_voted', true)
            ->where('voting.previous.members.0.choice', 'yes')
            ->where('voting.previous.members.0.display_name', $member->display_name)
            ->where('voting.previous.members.1.has_voted', false)
            ->where('voting.previous.members.1.choice', null)
            ->where('voting.previous.members.1.display_name', $pending->display_name)
            ->where('hall_display.stage', 'item'));
});

it('does not expose a previous result for an empty closed round', function (): void {
    $secretariat = previousVotingActor(UserRole::Secretariat, 'empty');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'requires_vote' => true,
        'voting_round' => 1,
        'voting_open_at' => null,
        'voting_closed_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voting.previous', null));
});

it('lets the secretariat pin and hide the previous voting result on the hall board', function (): void {
    Event::fake([HallDisplayChanged::class]);

    ['secretariat' => $secretariat, 'session' => $session, 'item' => $item] = sittingWithClosedVote('pin');

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.results', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('results')
        ->and($session->hall_display_agenda_item_id)->toBe($item->getKey());

    Event::assertDispatched(HallDisplayChanged::class, function (HallDisplayChanged $event) use ($session, $item): bool {
        return $event->session->is($session)
            && $event->stage === 'results'
            && $event->agendaItemId === $item->getKey();
    });

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hall_display.stage', 'results')
            ->where('hall_display.agenda_item_id', $item->getKey())
            ->where('voting.previous.tallies.yes', 1));

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('hall_display.stage', 'results')
            ->where('voting.previous.round', 1)
            ->where('can.control_hall_display', true));

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.item', $session))
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('item')
        ->and($session->hall_display_agenda_item_id)->toBeNull();
});

it('forbids board members from pinning a previous voting result', function (): void {
    ['member' => $member, 'session' => $session, 'item' => $item] = sittingWithClosedVote('denied');

    $this->actingAs($member)
        ->post(route('sessions.hall.results', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertForbidden();
});

it('rejects pinning when voting is still open', function (): void {
    ['secretariat' => $secretariat, 'session' => $session, 'item' => $item] = sittingWithClosedVote('open');

    $item->update([
        'voting_open_at' => now(),
        'voting_closed_at' => null,
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.results', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($session->fresh()->hall_display_stage)->toBe('item');
});

it('rejects pinning when the closed round has no ballots', function (): void {
    $secretariat = previousVotingActor(UserRole::Secretariat, 'noballot');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'requires_vote' => true,
        'voting_round' => 1,
        'voting_open_at' => null,
        'voting_closed_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.results', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('clears a pinned result when voting opens', function (): void {
    Event::fake([HallDisplayChanged::class]);

    ['secretariat' => $secretariat, 'session' => $session, 'item' => $item] = sittingWithClosedVote('reopen');

    $session->forceFill([
        'hall_display_stage' => 'results',
        'hall_display_agenda_item_id' => $item->getKey(),
    ])->save();

    $this->actingAs($secretariat)
        ->post(route('sessions.voting.open', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('item')
        ->and($session->hall_display_agenda_item_id)->toBeNull();
});

it('clears a pinned result when voting closes', function (): void {
    Event::fake([HallDisplayChanged::class]);

    $secretariat = previousVotingActor(UserRole::Secretariat, 'close-pin');
    $member = previousVotingActor(UserRole::BoardMember, 'close-member');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'requires_vote' => true,
        'voting_round' => 1,
        'voting_open_at' => now(),
        'voting_opened_at' => now(),
    ]);

    Vote::factory()->choice(VoteChoice::Yes)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $member->getKey(),
        'voting_round' => 1,
    ]);

    $session->forceFill([
        'hall_display_stage' => 'results',
        'hall_display_agenda_item_id' => $item->getKey(),
    ])->save();

    $this->actingAs($secretariat)
        ->post(route('sessions.voting.close', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('item')
        ->and($session->hall_display_agenda_item_id)->toBeNull();
});

it('clears a pinned result when the agenda advances', function (): void {
    Event::fake([HallDisplayChanged::class]);

    ['secretariat' => $secretariat, 'session' => $session, 'item' => $item] = sittingWithClosedVote('advance');

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'pending',
        'position' => 2,
        'title' => 'Next',
    ]);

    $session->forceFill([
        'hall_display_stage' => 'results',
        'hall_display_agenda_item_id' => $item->getKey(),
    ])->save();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('item')
        ->and($session->hall_display_agenda_item_id)->toBeNull();
});
