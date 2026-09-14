<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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

it('renders the audit index with paginated log data for authorized admins', function (): void {
    $admin = User::factory()->create([
        'email' => 'audit-admin@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::SystemAdministrator->value);

    app(AuditLogger::class)->record(
        event: 'test.audit.index',
        category: 'test',
        actor: $admin,
        message: 'Audit index fixture entry.',
    );

    $this->actingAs($admin)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audit/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.event', 'test.audit.index')
            ->has('can.verify'));
});

it('forbids users without audit.viewAny from the audit index', function (): void {
    $member = User::factory()->create([
        'email' => 'audit-member@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $this->actingAs($member)
        ->get(route('audit.index'))
        ->assertForbidden();
});
