<?php

use App\Enums\SessionGuestStatus;
use App\Enums\UserRole;
use App\Events\AttendanceUpdated;
use App\Events\SessionGuestsUpdated;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\SessionGuest;
use App\Models\User;
use App\Services\Sessions\QuorumService;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function guestActor(UserRole $role, string $suffix = ''): User
{
    $tag = $suffix === '' ? $role->value : "{$role->value}-{$suffix}";

    return User::factory()->create([
        'email' => "{$tag}-guest@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

it('lets secretariat add, edit, and remove an invited guest on the sitting', function (): void {
    Event::fake([SessionGuestsUpdated::class, AttendanceUpdated::class]);

    $secretariat = guestActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create();

    $this->actingAs($secretariat)
        ->post(route('sessions.guests.store', $session), [
            'name' => '  Maria   Santos  ',
            'organization' => '  Provincial Health Office  ',
            'speaking_topic' => '  Rabies program update  ',
        ])
        ->assertRedirect();

    $guest = SessionGuest::query()->where('session_id', $session->getKey())->first();

    expect($guest)->not->toBeNull()
        ->and($guest->name)->toBe('Maria Santos')
        ->and($guest->organization)->toBe('Provincial Health Office')
        ->and($guest->speaking_topic)->toBe('Rabies program update')
        ->and($guest->status)->toBe(SessionGuestStatus::Invited->value)
        ->and($guest->recorded_by)->toBe($secretariat->getKey());

    Event::assertDispatched(SessionGuestsUpdated::class);
    Event::assertNotDispatched(AttendanceUpdated::class);

    $this->actingAs($secretariat)
        ->put(route('sessions.guests.update', [$session, $guest]), [
            'name' => 'Maria Santos',
            'organization' => 'Provincial Health Office',
            'speaking_topic' => 'Rabies program update',
            'status' => SessionGuestStatus::Present->value,
        ])
        ->assertRedirect();

    expect($guest->fresh()?->status)->toBe(SessionGuestStatus::Present->value);

    $this->actingAs($secretariat)
        ->delete(route('sessions.guests.destroy', [$session, $guest]))
        ->assertRedirect();

    expect(SessionGuest::query()->find($guest->getKey()))->toBeNull();
});

it('lets the presiding officer add a guest and refuses a board member', function (): void {
    $officer = guestActor(UserRole::PresidingOfficer);
    $member = guestActor(UserRole::BoardMember);
    $session = LegislativeSession::factory()->create();

    $this->actingAs($member)
        ->post(route('sessions.guests.store', $session), ['name' => 'Juan Cruz'])
        ->assertForbidden();

    $this->actingAs($officer)
        ->post(route('sessions.guests.store', $session), ['name' => 'Juan Cruz'])
        ->assertRedirect();

    expect(SessionGuest::query()->where('session_id', $session->getKey())->count())->toBe(1);
});

it('shows guests to anyone who can open attendance and keeps them off the quorum count', function (): void {
    $member = guestActor(UserRole::BoardMember, 'viewer');
    $secretariat = guestActor(UserRole::Secretariat, 'viewer');
    $seated = guestActor(UserRole::BoardMember, 'seated');
    $session = LegislativeSession::factory()->create();

    SessionAttendance::factory()->present()->create([
        'session_id' => $session->getKey(),
        'user_id' => $seated->getKey(),
    ]);

    SessionGuest::factory()->create([
        'session_id' => $session->getKey(),
        'name' => 'Maria Santos',
        'organization' => 'Provincial Health Office',
        'speaking_topic' => 'Rabies program update',
        'status' => SessionGuestStatus::Present->value,
    ]);

    $this->actingAs($member)
        ->get(route('sessions.attendance.index', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Attendance')
            ->where('can.record', false)
            ->has('guests', 1)
            ->where('guests.0.name', 'Maria Santos')
            ->where('guests.0.status', SessionGuestStatus::Present->value)
            ->where('quorum.present_count', 1)
            ->where('quorum.present_members.0.display_name', $seated->display_name));

    $quorum = app(QuorumService::class)->forSession($session->fresh());

    expect($quorum->presentCount)->toBe(1)
        ->and(collect($quorum->presentMembers)->pluck('display_name'))->not->toContain('Maria Santos');

    $this->actingAs($secretariat)
        ->get(route('sessions.attendance.index', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.record', true));
});

it('allows two guests with the same name and keeps the order they were added', function (): void {
    $secretariat = guestActor(UserRole::Secretariat, 'order');
    $session = LegislativeSession::factory()->create();

    $newer = SessionGuest::factory()->create([
        'session_id' => $session->getKey(),
        'name' => 'Maria Santos',
        'organization' => 'Later office',
        'created_at' => now(),
    ]);
    $older = SessionGuest::factory()->create([
        'session_id' => $session->getKey(),
        'name' => 'Maria Santos',
        'organization' => 'Earlier office',
        'created_at' => now()->subHour(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.attendance.index', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('guests', 2)
            ->where('guests.0.id', $older->getKey())
            ->where('guests.0.organization', 'Earlier office')
            ->where('guests.1.id', $newer->getKey()));
});

it('rejects a blank name and a guest from another sitting', function (): void {
    $secretariat = guestActor(UserRole::Secretariat, 'blank');
    $session = LegislativeSession::factory()->create();
    $other = LegislativeSession::factory()->create();
    $guest = SessionGuest::factory()->create([
        'session_id' => $other->getKey(),
        'name' => 'Juan Cruz',
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.guests.store', $session), [
            'name' => '   ',
        ])
        ->assertSessionHasErrors('name');

    $this->actingAs($secretariat)
        ->delete(route('sessions.guests.destroy', [$session, $guest]))
        ->assertNotFound();

    expect($guest->fresh())->not->toBeNull();
});
