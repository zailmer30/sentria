<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
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

it('authorizes transcript broadcast channels for users with transcripts.view', function (): void {
    $user = User::factory()->create([
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $session = LegislativeSession::factory()->create();

    expect($user->can('view', $session))->toBeTrue()
        ->and($user->can('transcripts.view'))->toBeTrue();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-session-transcript.'.$session->getKey(),
        ])
        ->assertOk();
});

it('denies transcript broadcast channels for public users', function (): void {
    $user = User::factory()->create(['is_active' => true])
        ->assignRole(UserRole::PublicUser->value);

    $session = LegislativeSession::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-session-transcript.'.$session->getKey(),
        ])
        ->assertForbidden();
});

it('denies transcript broadcast channels for seated members without transcripts.view', function (): void {
    $user = User::factory()->create([
        'is_active' => true,
        'is_seated_member' => true,
    ]);
    $user->givePermissionTo('sessions.view');

    $session = LegislativeSession::factory()->create();

    expect($user->can('view', $session))->toBeTrue()
        ->and($user->can('transcripts.view'))->toBeFalse();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-session-transcript.'.$session->getKey(),
        ])
        ->assertForbidden();
});

it('requires both session view and transcripts.view for transcript channel access', function (): void {
    $session = LegislativeSession::factory()->create();
    $member = User::factory()->create(['is_active' => true, 'is_seated_member' => true])
        ->assignRole(UserRole::BoardMember->value);

    expect($member->can('view', $session))->toBeTrue()
        ->and($member->can('transcripts.view'))->toBeTrue();
});
