<?php

use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
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

function aiIdorActor(string $suffix, UserRole $role): User
{
    return User::factory()->create([
        'email' => "ai-idor-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('forbids accessing another users ai conversation by id', function (): void {
    $owner = aiIdorActor('owner', UserRole::BoardMember);
    $intruder = aiIdorActor('intruder', UserRole::BoardMember);

    $conversation = AiConversation::factory()->create([
        'user_id' => $owner->getKey(),
        'title' => 'Owner-only legislative question',
    ]);

    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->getKey(),
        'role' => 'user',
        'content' => 'SECRET-CONVERSATION-BODY-XYZZY',
    ]);

    $this->actingAs($intruder)
        ->getJson(route('ai.conversations.show', $conversation))
        ->assertForbidden()
        ->assertJsonMissing(['SECRET-CONVERSATION-BODY-XYZZY']);
});

it('allows owners to load their own ai conversation', function (): void {
    $owner = aiIdorActor('owner-ok', UserRole::BoardMember);

    $conversation = AiConversation::factory()->create([
        'user_id' => $owner->getKey(),
    ]);

    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->getKey(),
        'role' => 'user',
        'content' => 'Visible to owner',
    ]);

    $this->actingAs($owner)
        ->getJson(route('ai.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.id', $conversation->getKey());
});
