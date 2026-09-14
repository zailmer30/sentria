<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\Minutes;
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

function idorNoteActor(string $suffix): User
{
    return User::factory()->create([
        'email' => "idor-note-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::BoardMember->value);
}

it('blocks idor update and delete on private notes by id', function (): void {
    $owner = idorNoteActor('owner');
    $intruder = idorNoteActor('intruder');
    $session = LegislativeSession::factory()->create();

    $note = PrivateNote::factory()->create([
        'user_id' => $owner->getKey(),
        'notable_type' => LegislativeSession::class,
        'notable_id' => $session->getKey(),
        'body' => 'PRIVATE-NOTE-IDOR-SECRET',
    ]);

    $this->actingAs($intruder)
        ->put(route('private-notes.update', $note), ['body' => 'Stolen'])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('private-notes.destroy', $note))
        ->assertForbidden();

    expect($note->fresh()->body)->toBe('PRIVATE-NOTE-IDOR-SECRET');
});

it('returns 404 for draft minutes on the public portal', function (): void {
    $session = LegislativeSession::factory()->create(['is_public' => true]);

    $minutes = Minutes::factory()->aiDrafted()->create([
        'session_id' => $session->getKey(),
        'ai_draft' => 'DRAFT-MINUTES-SECRET-CONTENT',
    ]);

    $this->get(route('portal.minutes.show', $minutes))
        ->assertNotFound()
        ->assertDontSee('DRAFT-MINUTES-SECRET-CONTENT', false);
});

it('forbids users without minutes permission from viewing draft minutes internally', function (): void {
    $publicUser = User::factory()->create([
        'email' => 'public-idor@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::PublicUser->value);

    $minutes = Minutes::factory()->aiDrafted()->create([
        'ai_draft' => 'INTERNAL-DRAFT-SECRET',
    ]);

    $this->actingAs($publicUser)
        ->get(route('minutes.show', $minutes))
        ->assertForbidden();
});
