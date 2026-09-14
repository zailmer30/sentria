<?php

use App\Enums\UserRole;
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

it('shares role-appropriate permissions with the dashboard for each role', function (UserRole $role): void {
    $user = User::factory()->create([
        'email' => "{$role->value}-dash@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'display_name' => $role->label(),
    ])->assignRole($role->value);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('role', $role->value)
            ->where('auth.user.roles.0', $role->value)
            ->has('auth.user.permissions')
            ->where('ai.inherits_user_permissions', true)
        );
})->with(UserRole::cases());
