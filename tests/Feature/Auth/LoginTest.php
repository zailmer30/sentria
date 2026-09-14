<?php

use App\Enums\UserRole;
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

it('allows every seeded canonical role to log in', function (UserRole $role): void {
    $email = "{$role->value}@sentria.test";

    User::factory()->create([
        'email' => $email,
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $role->isSeatedMember(),
    ])->assignRole($role->value);

    $this->post('/login', [
        'email' => $email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated();
})->with(UserRole::cases());

it('rejects inactive accounts', function (): void {
    User::factory()->create([
        'email' => 'inactive@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => false,
    ])->assignRole(UserRole::BoardMember->value);

    $this->from('/login')->post('/login', [
        'email' => 'inactive@sentria.test',
        'password' => 'password',
    ])->assertRedirect('/login');

    $this->assertGuest();
});
