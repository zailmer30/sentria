<?php

use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\Vote;
use App\Services\Sessions\VotingService;
use App\States\Session\InSession;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

function votingActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-voting@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

function sessionReadyForVoting(): array
{
    $secretariat = votingActor(UserRole::Secretariat);
    $presiding = votingActor(UserRole::PresidingOfficer);
    $memberA = votingActor(UserRole::BoardMember);
    $memberB = votingActor(UserRole::CommitteeChair);

    $session = LegislativeSession::factory()->create([
        'session_number' => 'VS-100',
        'title' => 'Voting Session',
        'seated_member_count' => 12,
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'requires_vote' => true,
        'position' => 1,
        'item_number' => '1',
    ]);

    return compact('secretariat', 'presiding', 'memberA', 'memberB', 'session', 'item');
}

it('returns a single ballot when the same vote is submitted twice', function (): void {
    ['presiding' => $presiding, 'memberA' => $member, 'session' => $session, 'item' => $item] = sessionReadyForVoting();

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $item->getKey(),
    ])->assertRedirect();

    $item = $item->fresh();
    expect($item->voting_open_at)->not->toBeNull();

    $payload = [
        'agenda_item_id' => $item->getKey(),
        'choice' => VoteChoice::Yes->value,
        'voting_round' => $item->voting_round,
    ];

    $first = $this->actingAs($member)->postJson(route('sessions.voting.cast', $session), $payload);
    $first->assertCreated();

    $second = $this->actingAs($member)->postJson(route('sessions.voting.cast', $session), $payload);
    $second->assertOk();

    expect(Vote::query()->count())->toBe(1);
});

it('refuses duplicate vote ids with the same round and user', function (): void {
    ['presiding' => $presiding, 'memberA' => $member, 'session' => $session, 'item' => $item] = sessionReadyForVoting();

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $item->getKey(),
    ]);

    $item = $item->fresh();

    Vote::query()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $member->getKey(),
        'voting_round' => $item->voting_round,
        'choice' => VoteChoice::Yes->value,
        'cast_at' => now(),
    ]);

    $response = $this->actingAs($member)->postJson(route('sessions.voting.cast', $session), [
        'agenda_item_id' => $item->getKey(),
        'choice' => VoteChoice::Yes->value,
        'voting_round' => $item->voting_round,
    ]);

    $response->assertOk();
    expect(Vote::query()->count())->toBe(1);
});

it('lets a member change their ballot while voting is open', function (): void {
    ['presiding' => $presiding, 'memberA' => $member, 'session' => $session, 'item' => $item] = sessionReadyForVoting();

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $item->getKey(),
    ]);

    $item = $item->fresh();
    $payload = [
        'agenda_item_id' => $item->getKey(),
        'voting_round' => $item->voting_round,
    ];

    $this->actingAs($member)->postJson(route('sessions.voting.cast', $session), [
        ...$payload,
        'choice' => VoteChoice::Yes->value,
    ])->assertCreated();

    $this->actingAs($member)->postJson(route('sessions.voting.cast', $session), [
        ...$payload,
        'choice' => VoteChoice::No->value,
    ])->assertCreated();

    expect(Vote::query()->count())->toBe(2);

    $tallies = app(VotingService::class)->tallies($session, $item->fresh(), (int) $item->voting_round);

    expect($tallies)->toMatchArray([
        'yes' => 0,
        'no' => 1,
        'abstain' => 0,
        'inhibit' => 0,
        'total' => 1,
    ]);

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voting.user_vote', VoteChoice::No->value)
            ->where('voting.tallies.yes', 0)
            ->where('voting.tallies.no', 1)
            ->where('voting.tallies.total', 1));
});

it('refuses a ballot change after voting is closed', function (): void {
    ['presiding' => $presiding, 'memberA' => $member, 'session' => $session, 'item' => $item] = sessionReadyForVoting();

    $this->actingAs($presiding)->post(route('sessions.voting.open', $session), [
        'agenda_item_id' => $item->getKey(),
    ]);

    $item = $item->fresh();

    $this->actingAs($member)->postJson(route('sessions.voting.cast', $session), [
        'agenda_item_id' => $item->getKey(),
        'choice' => VoteChoice::Yes->value,
        'voting_round' => $item->voting_round,
    ])->assertCreated();

    $this->actingAs($presiding)->post(route('sessions.voting.close', $session), [
        'agenda_item_id' => $item->getKey(),
    ])->assertRedirect();

    $this->actingAs($member)->postJson(route('sessions.voting.cast', $session), [
        'agenda_item_id' => $item->getKey(),
        'choice' => VoteChoice::No->value,
        'voting_round' => $item->voting_round,
    ])->assertUnprocessable();

    expect(Vote::query()->count())->toBe(1)
        ->and(Vote::query()->firstOrFail()->choice)->toBe(VoteChoice::Yes->value);
});
