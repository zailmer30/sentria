<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Events\AttendanceUpdated;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;
use App\States\Session\Suspended;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function floorAttendanceActor(UserRole $role, string $suffix, bool $seated = true): User
{
    return User::factory()->create([
        'email' => "{$role->value}-floor-attend-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $seated,
    ])->assignRole($role->value);
}

function floorAttendanceRecord(LegislativeSession $session, User $member): ?SessionAttendance
{
    return SessionAttendance::query()
        ->where('session_id', $session->getKey())
        ->where('user_id', $member->getKey())
        ->first();
}

it('marks a seated member present when they open the live paperless floor', function (): void {
    Event::fake([AttendanceUpdated::class]);

    $member = floorAttendanceActor(UserRole::BoardMember, 'present');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    $record = floorAttendanceRecord($session, $member);

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe(AttendanceStatus::Present->value)
        ->and($record->check_in_method)->toBe('tablet')
        ->and($record->recorded_by)->toBe($member->getKey())
        ->and($record->checked_in_at)->not->toBeNull();

    Event::assertDispatched(AttendanceUpdated::class);
});

it('does not broadcast again when the member reloads the paperless floor', function (): void {
    Event::fake([AttendanceUpdated::class]);

    $member = floorAttendanceActor(UserRole::BoardMember, 'reload');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    expect(floorAttendanceRecord($session, $member)?->status)->toBe(AttendanceStatus::Present->value);

    Event::assertDispatchedTimes(AttendanceUpdated::class, 1);
});

it('does not overwrite {status} when the member opens the paperless floor', function (AttendanceStatus $status): void {
    Event::fake([AttendanceUpdated::class]);

    $member = floorAttendanceActor(UserRole::BoardMember, $status->value);
    $session = LegislativeSession::factory()->inSession()->create();

    SessionAttendance::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'status' => $status->value,
        'checked_in_at' => $status->countsTowardQuorum() ? now()->subMinutes(10) : null,
        'check_in_method' => $status->countsTowardQuorum() ? 'manual' : null,
        'recorded_by' => $member->getKey(),
    ]);

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    $record = floorAttendanceRecord($session, $member);

    expect($record?->status)->toBe($status->value)
        ->and($record?->check_in_method)->toBe($status->countsTowardQuorum() ? 'manual' : null);

    Event::assertNotDispatched(AttendanceUpdated::class);
})->with([
    AttendanceStatus::Excused,
    AttendanceStatus::OnOfficialBusiness,
    AttendanceStatus::Late,
    AttendanceStatus::Present,
]);

it('does not add a non-seated user as present on the member floor', function (): void {
    $clerk = floorAttendanceActor(UserRole::Secretariat, 'staff', seated: false);
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($clerk)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    expect(floorAttendanceRecord($session, $clerk))->toBeNull();
});

it('does not auto-check-in a seated member from the hall dashboard', function (): void {
    $member = floorAttendanceActor(UserRole::BoardMember, 'dashboard');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($member)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk();

    expect(floorAttendanceRecord($session, $member)?->status)->toBe(AttendanceStatus::Absent->value);
});

it('does not auto-check-in a seated member from the secretariat floor', function (): void {
    $member = floorAttendanceActor(UserRole::BoardMember, 'secretariat-floor');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($member)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk();

    expect(floorAttendanceRecord($session, $member)?->status)->toBe(AttendanceStatus::Absent->value);
});

it('does not check in when the sitting is not live', function (): void {
    $member = floorAttendanceActor(UserRole::BoardMember, 'scheduled');
    $session = LegislativeSession::factory()->scheduled()->create();

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    expect(floorAttendanceRecord($session, $member))->toBeNull();
});

it('marks a seated member present on a suspended sitting', function (): void {
    $member = floorAttendanceActor(UserRole::BoardMember, 'suspended');
    $session = LegislativeSession::factory()->inSession()->create([
        'status' => Suspended::$name,
    ]);

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    expect(floorAttendanceRecord($session, $member)?->status)->toBe(AttendanceStatus::Present->value)
        ->and(floorAttendanceRecord($session, $member)?->check_in_method)->toBe('tablet');
});

it('lets secretariat override tablet check-in afterward', function (): void {
    $member = floorAttendanceActor(UserRole::BoardMember, 'override');
    $secretariat = floorAttendanceActor(UserRole::Secretariat, 'clerk', seated: false);
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    expect(floorAttendanceRecord($session, $member)?->status)->toBe(AttendanceStatus::Present->value);

    $this->actingAs($secretariat)
        ->put(route('sessions.attendance.update', $session), [
            'records' => [[
                'user_id' => $member->getKey(),
                'status' => AttendanceStatus::Late->value,
            ]],
        ])
        ->assertRedirect();

    $late = floorAttendanceRecord($session, $member);

    expect($late?->status)->toBe(AttendanceStatus::Late->value)
        ->and($late?->check_in_method)->toBe('manual');

    $this->actingAs($secretariat)
        ->put(route('sessions.attendance.update', $session), [
            'records' => [[
                'user_id' => $member->getKey(),
                'status' => AttendanceStatus::Absent->value,
            ]],
        ])
        ->assertRedirect();

    expect(floorAttendanceRecord($session, $member)?->status)->toBe(AttendanceStatus::Absent->value);
});

it('clears an absence reason when the member checks in from the paperless floor', function (): void {
    Event::fake([AttendanceUpdated::class]);

    $member = floorAttendanceActor(UserRole::BoardMember, 'remarks');
    $session = LegislativeSession::factory()->inSession()->create();

    SessionAttendance::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'status' => AttendanceStatus::Absent->value,
        'remarks' => 'in hospital',
    ]);

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    $record = floorAttendanceRecord($session, $member);

    expect($record?->status)->toBe(AttendanceStatus::Present->value)
        ->and($record?->remarks)->toBeNull();
});
