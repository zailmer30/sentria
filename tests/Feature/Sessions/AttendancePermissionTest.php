<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\LegislativeSession;
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

function attendanceActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-attendance@sentria.test",
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
