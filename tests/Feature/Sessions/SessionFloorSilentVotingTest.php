<?php

use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Events\VotingOpened;
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

function silentVotingActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-silent-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $role !== UserRole::Secretariat,
        'display_name' => "Hon. {$suffix}",
    ])->assignRole($role->value);
}

/**
 * @return array{secretariat: User, presiding: User, member: User, pending: User, session: LegislativeSession, item: AgendaItem}
 */
function sittingReadyForSilentVote(string $suffix = 'open'): array
{
    $secretariat = silentVotingActor(UserRole::Secretariat, "sec-{$suffix}");
    $presiding = silentVotingActor(UserRole::PresidingOfficer, "chair-{$suffix}");
    $member = silentVotingActor(UserRole::BoardMember, "Reyes-{$suffix}");
    $pending = silentVotingActor(UserRole::BoardMember, "Santos-{$suffix}");

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
        'seated_member_count' => 12,
        'presiding_officer_id' => $presiding->getKey(),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'requires_vote' => true,
        'title' => 'Silent measure',
    ]);

    SessionAttendance::factory()->present()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
    ]);
    SessionAttendance::factory()->present()->create([
        'session_id' => $session->getKey(),
        'user_id' => $pending->getKey(),
    ]);

    return compact('secretariat', 'presiding', 'member', 'pending', 'session', 'item');
}

it('opens a silent round when the checkbox is posted, and a named round by default', function (): void {
    ['presiding' => $presiding, 'member' => $member, 'session' => $session, 'item' => $item] = sittingReadyForSilentVote('flag');

    Event::fake([VotingOpened::class]);

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $item->getKey(),
        'silent' => true,
    ])->assertRedirect();

    $item = $item->fresh();
    $firstRound = (int) $item->voting_round;
    expect($item->isSilentVotingRound($firstRound))->toBeTrue()
        ->and($item->silent_voting_rounds)->toContain($firstRound);

    Event::assertDispatched(VotingOpened::class, fn (VotingOpened $event): bool => $event->silent === true);

    Vote::factory()->choice(VoteChoice::Yes)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $member->getKey(),
        'voting_round' => $firstRound,
    ]);

    $this->actingAs($presiding)->post(route('sessions.voting.close', $session), [
        'agenda_item_id' => $item->getKey(),
    ])->assertRedirect();

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $item->getKey(),
    ])->assertRedirect();

    $item = $item->fresh();
    expect($item->isSilentVotingRound((int) $item->voting_round))->toBeFalse()
        ->and($item->isSilentVotingRound($firstRound))->toBeTrue();
});

it('keeps the named roll for the secretariat during a silent vote and masks it for members', function (): void {
    [
        'secretariat' => $secretariat,
        'presiding' => $presiding,
        'member' => $member,
        'pending' => $pending,
        'session' => $session,
        'item' => $item,
    ] = sittingReadyForSilentVote('mask');

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $item->getKey(),
        'silent' => true,
    ])->assertRedirect();

    $item = $item->fresh();

    Vote::factory()->choice(VoteChoice::Yes)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $member->getKey(),
        'voting_round' => $item->voting_round,
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voting.open', true)
            ->where('voting.silent', true)
            ->where('voting.awaiting_count', 1)
            ->has('voting.members')
            ->where(
                'voting.members',
                fn ($members): bool => collect($members)->contains(
                    fn ($row): bool => ($row['display_name'] ?? null) === $member->display_name && ($row['choice'] ?? null) === 'yes',
                ) && collect($members)->contains(
                    fn ($row): bool => ($row['display_name'] ?? null) === $pending->display_name && ($row['has_voted'] ?? false) === false,
                ),
            ));

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voting.open', true)
            ->where('voting.silent', true)
            ->where('voting.user_vote', VoteChoice::Yes->value)
            ->where('voting.tallies.yes', 1)
            ->where('voting.awaiting_count', 1)
            ->where('voting.members', []));

    $this->actingAs($presiding)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voting.silent', true)
            ->where(
                'voting.members',
                fn ($members): bool => collect($members)->contains(
                    fn ($row): bool => ($row['display_name'] ?? null) === $member->display_name && ($row['choice'] ?? null) === 'yes',
                ),
            ));
});

it('hides a silent previous roll from members until the clerk would pin it, and keeps names for the clerk', function (): void {
    [
        'secretariat' => $secretariat,
        'presiding' => $presiding,
        'member' => $member,
        'pending' => $pending,
        'session' => $session,
        'item' => $item,
    ] = sittingReadyForSilentVote('previous');

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $item->getKey(),
        'silent' => true,
    ])->assertRedirect();

    $item = $item->fresh();

    Vote::factory()->choice(VoteChoice::No)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $member->getKey(),
        'voting_round' => $item->voting_round,
    ]);

    $this->actingAs($presiding)->post(route('sessions.voting.close', $session), [
        'agenda_item_id' => $item->getKey(),
    ])->assertRedirect();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voting.open', false)
            ->where('voting.silent', false)
            ->where('voting.previous.silent', true)
            ->where('voting.previous.tallies.no', 1)
            ->has('voting.previous.members')
            ->where(
                'voting.previous.members',
                fn ($members): bool => collect($members)->contains(
                    fn ($row): bool => ($row['display_name'] ?? null) === $member->display_name && ($row['choice'] ?? null) === 'no',
                ) && collect($members)->contains(
                    fn ($row): bool => ($row['display_name'] ?? null) === $pending->display_name,
                ),
            ));

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voting.previous.silent', true)
            ->where('voting.previous.tallies.no', 1)
            ->where('voting.previous.members', []));
});
