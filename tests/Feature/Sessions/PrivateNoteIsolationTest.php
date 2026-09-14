<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\PrivateNote;
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

function noteActor(string $suffix): User
{
    return User::factory()->create([
        'email' => "note-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::BoardMember->value);
}

it('keeps private notes owner-only for update and delete', function (): void {
    $owner = noteActor('owner');
    $other = noteActor('other');
    $session = LegislativeSession::factory()->create();

    $note = PrivateNote::factory()->create([
        'user_id' => $owner->getKey(),
        'notable_type' => LegislativeSession::class,
        'notable_id' => $session->getKey(),
        'body' => 'Owner-only annotation',
    ]);

    $this->actingAs($other)
        ->put(route('private-notes.update', $note), ['body' => 'Hijacked'])
        ->assertForbidden();

    $this->actingAs($other)
        ->delete(route('private-notes.destroy', $note))
        ->assertForbidden();

    expect($note->fresh()->body)->toBe('Owner-only annotation');
});

it('allows owners to update their own private notes', function (): void {
    $owner = noteActor('owner-update');
    $session = LegislativeSession::factory()->create();

    $note = PrivateNote::factory()->create([
        'user_id' => $owner->getKey(),
        'notable_type' => LegislativeSession::class,
        'notable_id' => $session->getKey(),
        'body' => 'Original',
    ]);

    $this->actingAs($owner)
        ->put(route('private-notes.update', $note), ['body' => 'Updated'])
        ->assertRedirect();

    expect($note->fresh()->body)->toBe('Updated');
});
