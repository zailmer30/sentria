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

it('authenticates an external API consumer with a sanctum token', function (): void {
    $user = User::factory()->create([
        'email' => 'api-consumer@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    $token = $user->createToken('integration-smoke', ['documents:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', 'api-consumer@sentria.test');
});

it('rejects unauthenticated api access', function (): void {
    $this->getJson('/api/user')->assertUnauthorized();
});
