<?php

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function floorMotionActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-floor-motion-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

/**
 * @return array{mover: User, seconder: User, session: LegislativeSession, item: AgendaItem}
 */
function floorMotionSitting(): array
{
    $mover = floorMotionActor(UserRole::BoardMember, 'mover');
    $seconder = floorMotionActor(UserRole::BoardMember, 'seconder');

    $session = LegislativeSession::factory()->inSession()->create([
        'seated_member_count' => 12,
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'item_number' => '4',
        'title' => 'Calendar of Business',
    ]);

    return compact('mover', 'seconder', 'session', 'item');
}

it('exposes motion create on the member floor while the sitting is open', function (): void {
    ['mover' => $member, 'session' => $session] = floorMotionSitting();

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/BoardMember')
            ->where('can.create_motion', true)
            ->has('motions', 0));
});

it('lets a board member raise a motion attributed to themselves', function (): void {
    ['mover' => $member, 'session' => $session, 'item' => $item] = floorMotionSitting();

    $this->actingAs($member)
        ->from(route('sessions.floor.member', $session))
        ->post(route('sessions.motions.store', $session), [
            'agenda_item_id' => $item->getKey(),
            'text' => 'I move that the measure be approved.',
            'type' => 'main',
        ])
        ->assertRedirect();

    $motion = Motion::query()->sole();

    expect($motion->moved_by)->toBe($member->getKey())
        ->and($motion->agenda_item_id)->toBe($item->getKey())
        ->and($motion->status)->toBe('proposed')
        ->and($motion->text)->toBe('I move that the measure be approved.');

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('motions', 1)
            ->where('motions.0.id', $motion->getKey())
            ->where('motions.0.mover', $member->display_name)
            ->where('motions.0.can.second', false)
            ->where('motions.0.can.withdraw', true)
            ->where('motions.0.can.rule', false));
});

it('lets a board member second another member\'s proposed motion', function (): void {
    ['mover' => $mover, 'seconder' => $seconder, 'session' => $session, 'item' => $item] = floorMotionSitting();

    $motion = Motion::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'moved_by' => $mover->getKey(),
        'status' => 'proposed',
        'text' => 'I move that the matter be referred to committee.',
    ]);

    $this->actingAs($seconder)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('motions.0.can.second', true)
            ->where('motions.0.can.withdraw', false)
            ->where('motions.0.can.rule', false));

    $this->actingAs($seconder)
        ->from(route('sessions.floor.member', $session))
        ->post(route('sessions.motions.second', [$session, $motion]))
        ->assertRedirect();

    expect($motion->fresh())
        ->status->toBe('seconded')
        ->seconded_by->toBe($seconder->getKey());
});

it('forbids a board member from seconding their own motion or ruling', function (): void {
    ['mover' => $member, 'session' => $session, 'item' => $item] = floorMotionSitting();

    $motion = Motion::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'moved_by' => $member->getKey(),
        'status' => 'proposed',
    ]);

    $this->actingAs($member)
        ->post(route('sessions.motions.second', [$session, $motion]))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('sessions.motions.rule', [$session, $motion]), [
            'disposition' => 'carried',
        ])
        ->assertForbidden();
});
