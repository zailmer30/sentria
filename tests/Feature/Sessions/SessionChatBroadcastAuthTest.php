<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\SessionConversation;
use App\Models\SessionConversationParticipant;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
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

function chatChannelActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-chat-channel-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $role !== UserRole::Secretariat,
    ])->assignRole($role->value);
}

it('authorizes a participant on the private conversation channel', function (): void {
    $alice = chatChannelActor(UserRole::BoardMember, 'in');
    $bob = chatChannelActor(UserRole::BoardMember, 'peer');
    $session = LegislativeSession::factory()->inSession()->create();

    $conversation = SessionConversation::factory()->create([
        'session_id' => $session->getKey(),
        'created_by' => $alice->getKey(),
        'direct_pair_key' => SessionConversation::directPairKey($alice->getKey(), $bob->getKey()),
    ]);

    foreach ([$alice, $bob] as $user) {
        SessionConversationParticipant::query()->create([
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'joined_at' => now(),
        ]);
    }

    $this->actingAs($alice)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-session-chat.'.$conversation->getKey(),
        ])
        ->assertOk();
});

it('denies a non-participant and a legal reviewer on the conversation channel', function (): void {
    $alice = chatChannelActor(UserRole::BoardMember, 'owner');
    $bob = chatChannelActor(UserRole::BoardMember, 'peer2');
    $outsider = chatChannelActor(UserRole::BoardMember, 'spy');
    $legal = chatChannelActor(UserRole::LegalTechnicalReviewer, 'legal');
    $session = LegislativeSession::factory()->inSession()->create();

    $conversation = SessionConversation::factory()->create([
        'session_id' => $session->getKey(),
        'created_by' => $alice->getKey(),
        'direct_pair_key' => SessionConversation::directPairKey($alice->getKey(), $bob->getKey()),
    ]);

    foreach ([$alice, $bob] as $user) {
        SessionConversationParticipant::query()->create([
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'joined_at' => now(),
        ]);
    }

    $this->actingAs($outsider)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-session-chat.'.$conversation->getKey(),
        ])
        ->assertForbidden();

    $this->actingAs($legal)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-session-chat.'.$conversation->getKey(),
        ])
        ->assertForbidden();
});
