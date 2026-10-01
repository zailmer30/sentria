<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function attendanceActor(UserRole $role, string $suffix = ''): User
{
    $tag = $suffix === '' ? $role->value : "{$role->value}-{$suffix}";

    return User::factory()->create([
        'email' => "{$tag}-attendance@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

it('requires attendance.record permission to update attendance', function (): void {
    $member = attendanceActor(UserRole::BoardMember);
    $secretariat = attendanceActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create();

    $payload = [
        'records' => [[
            'user_id' => $member->getKey(),
            'status' => AttendanceStatus::Present->value,
        ]],
    ];

    $this->actingAs($member)
        ->put(route('sessions.attendance.update', $session), $payload)
        ->assertForbidden();

    $this->actingAs($secretariat)
        ->put(route('sessions.attendance.update', $session), $payload)
        ->assertRedirect();
});

it('allows viewing attendance with attendance.viewAny permission', function (): void {
    $member = attendanceActor(UserRole::BoardMember);
    $session = LegislativeSession::factory()->create();

    $this->actingAs($member)
        ->get(route('sessions.attendance.index', $session))
        ->assertOk();
});

it('saves an optional reason when a member is not in the chamber', function (): void {
    $member = attendanceActor(UserRole::BoardMember, 'remarks');
    $secretariat = attendanceActor(UserRole::Secretariat, 'remarks');
    $session = LegislativeSession::factory()->create();

    $this->actingAs($secretariat)
        ->put(route('sessions.attendance.update', $session), [
            'records' => [[
                'user_id' => $member->getKey(),
                'status' => AttendanceStatus::Absent->value,
                'remarks' => '  in hospital  ',
            ]],
        ])
        ->assertRedirect();

    $row = SessionAttendance::query()
        ->where('session_id', $session->getKey())
        ->where('user_id', $member->getKey())
        ->first();

    expect($row?->status)->toBe(AttendanceStatus::Absent->value)
        ->and($row?->remarks)->toBe('in hospital');
});

it('keeps the reason when moving among not-in-chamber statuses without sending remarks', function (): void {
    $member = attendanceActor(UserRole::BoardMember, 'keep');
    $secretariat = attendanceActor(UserRole::Secretariat, 'keep');
    $session = LegislativeSession::factory()->create();

    SessionAttendance::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'status' => AttendanceStatus::Absent->value,
        'remarks' => 'committee hearing in Manila',
    ]);

    $this->actingAs($secretariat)
        ->put(route('sessions.attendance.update', $session), [
            'records' => [[
                'user_id' => $member->getKey(),
                'status' => AttendanceStatus::Excused->value,
            ]],
        ])
        ->assertRedirect();

    $row = SessionAttendance::query()
        ->where('session_id', $session->getKey())
        ->where('user_id', $member->getKey())
        ->first();

    expect($row?->status)->toBe(AttendanceStatus::Excused->value)
        ->and($row?->remarks)->toBe('committee hearing in Manila');
});

it('clears the reason when the member is marked present or late', function (): void {
    $member = attendanceActor(UserRole::BoardMember, 'clear');
    $secretariat = attendanceActor(UserRole::Secretariat, 'clear');
    $session = LegislativeSession::factory()->create();

    SessionAttendance::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'status' => AttendanceStatus::Absent->value,
        'remarks' => 'in hospital',
    ]);

    $this->actingAs($secretariat)
        ->put(route('sessions.attendance.update', $session), [
            'records' => [[
                'user_id' => $member->getKey(),
                'status' => AttendanceStatus::Present->value,
            ]],
        ])
        ->assertRedirect();

    expect(SessionAttendance::query()
        ->where('session_id', $session->getKey())
        ->where('user_id', $member->getKey())
        ->value('remarks'))->toBeNull();
});

it('clears the reason when an empty note is saved', function (): void {
    $member = attendanceActor(UserRole::BoardMember, 'empty');
    $secretariat = attendanceActor(UserRole::Secretariat, 'empty');
    $session = LegislativeSession::factory()->create();

    SessionAttendance::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'status' => AttendanceStatus::Absent->value,
        'remarks' => 'in hospital',
    ]);

    $this->actingAs($secretariat)
        ->put(route('sessions.attendance.update', $session), [
            'records' => [[
                'user_id' => $member->getKey(),
                'status' => AttendanceStatus::Absent->value,
                'remarks' => '',
            ]],
        ])
        ->assertRedirect();

    expect(SessionAttendance::query()
        ->where('session_id', $session->getKey())
        ->where('user_id', $member->getKey())
        ->value('remarks'))->toBeNull();
});
