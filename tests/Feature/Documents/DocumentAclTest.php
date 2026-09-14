<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\DocumentVersion;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
});

function aclActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-acl@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('blocks confidential document view and download without grant at policy level', function (): void {
    $member = aclActor(UserRole::BoardMember);
    $author = aclActor(UserRole::Secretariat);

    $document = Document::factory()->confidential()->create(['author_id' => $author->getKey()]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => 'documents/acl/secret.txt',
    ]);

    Storage::disk('local')->put('documents/acl/secret.txt', 'secret');

    expect($member->can('view', $document))->toBeFalse()
        ->and($member->can('download', $document))->toBeFalse();

    DocumentGrant::factory()->create([
        'document_id' => $document->getKey(),
        'user_id' => $member->getKey(),
        'role_id' => null,
        'committee_id' => null,
        'granted_by' => $author->getKey(),
        'granted_at' => now(),
    ]);

    expect($member->can('view', $document))->toBeTrue()
        ->and($member->can('download', $document))->toBeTrue();
});

it('allows internal documents for permitted roles without extra grants', function (): void {
    $member = aclActor(UserRole::BoardMember);

    $document = Document::factory()->create([
        'confidentiality' => Confidentiality::Internal->value,
    ]);

    expect($member->can('view', $document))->toBeTrue();
});

it('denies public user from listing documents', function (): void {
    $publicUser = aclActor(UserRole::PublicUser);

    expect($publicUser->can('viewAny', Document::class))->toBeFalse();

    $this->actingAs($publicUser)
        ->get(route('documents.index'))
        ->assertForbidden();
});

it('returns forbidden for confidential document show and download without grant', function (): void {
    $member = aclActor(UserRole::BoardMember);
    $author = aclActor(UserRole::Secretariat);

    $document = Document::factory()->confidential()->create([
        'author_id' => $author->getKey(),
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => 'documents/acl/secret.txt',
        'mime_type' => 'text/plain',
    ]);

    Storage::disk('local')->put('documents/acl/secret.txt', 'secret');

    $this->actingAs($member)
        ->get(route('documents.show', $document))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('documents.versions.download', [$document, $version]))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('documents.versions.preview', [$document, $version]))
        ->assertForbidden();
});

it('allows confidential document access after explicit user grant', function (): void {
    $member = aclActor(UserRole::BoardMember);
    $author = aclActor(UserRole::Secretariat);

    $document = Document::factory()->confidential()->create([
        'author_id' => $author->getKey(),
    ]);

    DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => 'documents/acl/granted.txt',
        'mime_type' => 'text/plain',
    ]);

    Storage::disk('local')->put('documents/acl/granted.txt', 'granted');

    DocumentGrant::factory()->create([
        'document_id' => $document->getKey(),
        'user_id' => $member->getKey(),
        'role_id' => null,
        'committee_id' => null,
        'granted_by' => $author->getKey(),
        'granted_at' => now(),
    ]);

    $this->actingAs($member)
        ->get(route('documents.show', $document))
        ->assertOk();
});
