<?php

use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;
use App\Models\Vote;
use App\States\Session\InSession;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('public');
});

function votingDisplayActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-vote-display-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
        'display_name' => "Hon. {$suffix}",
    ])->assignRole($role->value);
}

it('shows present members with photos on the chamber dashboard while voting is open', function (): void {
    $viewer = votingDisplayActor(UserRole::Secretariat, 'sec');
    $viewer->forceFill(['is_seated_member' => false])->save();

    $voted = votingDisplayActor(UserRole::BoardMember, 'Reyes');
    $pending = votingDisplayActor(UserRole::BoardMember, 'Santos');

    $votedPath = UploadedFile::fake()->image('reyes.jpg', 200, 200)->store('avatars', 'public');
    $voted->forceFill(['avatar_path' => $votedPath])->save();

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
        'voting_open_at' => now(),
    ]);

    SessionAttendance::factory()->present()->create([
        'session_id' => $session->getKey(),
        'user_id' => $voted->getKey(),
    ]);
    SessionAttendance::factory()->present()->create([
        'session_id' => $session->getKey(),
        'user_id' => $pending->getKey(),
    ]);

    Vote::factory()->choice(VoteChoice::Yes)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $voted->getKey(),
        'voting_round' => 1,
    ]);

    $this->actingAs($viewer)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Dashboard')
            ->where('voting.open', true)
            ->has('voting.members', 2)
            ->where('voting.members.0.has_voted', true)
            ->where('voting.members.0.choice', 'yes')
            ->where('voting.members.0.display_name', $voted->display_name)
            ->where('voting.members.0.avatar_url', $voted->avatarUrl())
            ->where('voting.members.1.has_voted', false)
            ->where('voting.members.1.choice', null)
            ->where('voting.members.1.display_name', $pending->display_name)
            ->where('voting.members.1.avatar_url', null)
            ->where('attendance.0.avatar_url', $voted->avatarUrl()));
});
