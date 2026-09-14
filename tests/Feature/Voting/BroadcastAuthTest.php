<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

it('authorizes session broadcast channels for permitted viewers', function (): void {
    $user = User::factory()->create([
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $session = LegislativeSession::factory()->create();

    expect(Gate::forUser($user)->check('view', $session))->toBeTrue();
});

it('denies session broadcast channels for public users', function (): void {
    $user = User::factory()->create(['is_active' => true])
        ->assignRole(UserRole::PublicUser->value);

    $session = LegislativeSession::factory()->create();

    expect(Gate::forUser($user)->check('view', $session))->toBeFalse();
});

it('denies session broadcast channels for guests', function (): void {
    $session = LegislativeSession::factory()->create();

    expect(Gate::check('view', $session))->toBeFalse();
});
