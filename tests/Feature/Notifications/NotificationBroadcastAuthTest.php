<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app',
        'broadcasting.connections.pusher.options.cluster' => 'mt1',
        'broadcasting.connections.pusher.options.useTLS' => true,
    ]);

    require base_path('routes/channels.php');
});

it('authorizes a user on their own notification broadcast channel', function (): void {
    $user = User::factory()->create(['is_active' => true])
        ->assignRole(UserRole::BoardMember->value);

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$user->getKey(),
        ])
        ->assertOk();
});

it('denies a user access to another users notification broadcast channel', function (): void {
    $user = User::factory()->create(['is_active' => true])
        ->assignRole(UserRole::BoardMember->value);
    $other = User::factory()->create(['is_active' => true])
        ->assignRole(UserRole::Secretariat->value);

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$other->getKey(),
        ])
        ->assertForbidden();
});

it('denies guests access to user notification broadcast channels', function (): void {
    $user = User::factory()->create(['is_active' => true])
        ->assignRole(UserRole::BoardMember->value);

    $this->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-App.Models.User.'.$user->getKey(),
    ])->assertForbidden();
});
