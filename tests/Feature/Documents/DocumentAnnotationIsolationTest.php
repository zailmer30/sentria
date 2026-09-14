<?php

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\DocumentGrant;
use App\Models\DocumentVersion;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
});

function annotationActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-annot-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function annotationPdf(Document $document, string $suffix): DocumentVersion
{
    $path = "documents/annotations/{$suffix}.pdf";

    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");

    return DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'filing.pdf',
        'mime_type' => 'application/pdf',
        'scan_status' => 'skipped',
    ]);
}

it('saves annotations for the acting user only', function (): void {
    $owner = annotationActor(UserRole::Secretariat, 'owner');
    $other = annotationActor(UserRole::Secretariat, 'other');
    $document = Document::factory()->create(['author_id' => $owner->getKey()]);
    $version = annotationPdf($document, 'owner');

    $payload = [
        ['annotation' => ['id' => 'owner-highlight', 'type' => 8, 'pageIndex' => 0]],
    ];

    $this->actingAs($owner)
        ->putJson(route('documents.versions.annotations.update', [$document, $version]), [
            'payload' => $payload,
        ])
        ->assertOk()
        ->assertJson(['saved' => true]);

    expect(DocumentAnnotation::query()->count())->toBe(1)
        ->and(DocumentAnnotation::query()->first()?->user_id)->toBe($owner->getKey())
        ->and(DocumentAnnotation::query()->first()?->payload)->toBe($payload);

    $this->actingAs($other)
        ->get(route('documents.versions.view', [$document, $version]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/View')
            ->where('annotations', [])
            ->missing('annotations.0.annotation.id'));
});

it('returns only the viewer\'s annotations on the document view page', function (): void {
    $owner = annotationActor(UserRole::Secretariat, 'view-owner');
    $other = annotationActor(UserRole::Secretariat, 'view-other');
    $document = Document::factory()->create(['author_id' => $owner->getKey()]);
    $version = annotationPdf($document, 'view');

    DocumentAnnotation::factory()->create([
        'user_id' => $owner->getKey(),
        'document_version_id' => $version->getKey(),
        'payload' => [['annotation' => ['id' => 'mine', 'type' => 8]]],
    ]);

    DocumentAnnotation::factory()->create([
        'user_id' => $other->getKey(),
        'document_version_id' => $version->getKey(),
        'payload' => [['annotation' => ['id' => 'theirs', 'type' => 8]]],
    ]);

    $this->actingAs($owner)
        ->get(route('documents.versions.view', [$document, $version]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/View')
            ->where('annotations.0.annotation.id', 'mine')
            ->has('annotations', 1));
});

it('does not let another user overwrite the owner\'s annotations', function (): void {
    $owner = annotationActor(UserRole::Secretariat, 'idor-owner');
    $intruder = annotationActor(UserRole::Secretariat, 'idor-intruder');
    $document = Document::factory()->create(['author_id' => $owner->getKey()]);
    $version = annotationPdf($document, 'idor');

    $ownerPayload = [['annotation' => ['id' => 'OWNER-ONLY-MARK', 'type' => 8]]];

    DocumentAnnotation::factory()->create([
        'user_id' => $owner->getKey(),
        'document_version_id' => $version->getKey(),
        'payload' => $ownerPayload,
    ]);

    $this->actingAs($intruder)
        ->putJson(route('documents.versions.annotations.update', [$document, $version]), [
            'payload' => [['annotation' => ['id' => 'stolen', 'type' => 8]]],
        ])
        ->assertOk();

    expect(DocumentAnnotation::query()->where('user_id', $owner->getKey())->first()?->payload)
        ->toBe($ownerPayload)
        ->and(DocumentAnnotation::query()->where('user_id', $intruder->getKey())->first()?->payload)
        ->toBe([['annotation' => ['id' => 'stolen', 'type' => 8]]]);
});

it('forbids annotation saves without a download grant', function (): void {
    $member = annotationActor(UserRole::BoardMember, 'nogrant');
    $author = annotationActor(UserRole::Secretariat, 'grant-author');
    $document = Document::factory()->confidential()->create(['author_id' => $author->getKey()]);
    $version = annotationPdf($document, 'secret');

    $this->actingAs($member)
        ->putJson(route('documents.versions.annotations.update', [$document, $version]), [
            'payload' => [['annotation' => ['id' => 'blocked', 'type' => 8]]],
        ])
        ->assertForbidden();

    expect(DocumentAnnotation::query()->count())->toBe(0);

    DocumentGrant::factory()->create([
        'document_id' => $document->getKey(),
        'user_id' => $member->getKey(),
        'role_id' => null,
        'committee_id' => null,
        'granted_by' => $author->getKey(),
        'granted_at' => now(),
        'ability' => 'download',
    ]);

    $this->actingAs($member)
        ->putJson(route('documents.versions.annotations.update', [$document, $version]), [
            'payload' => [['annotation' => ['id' => 'granted-mark', 'type' => 8]]],
        ])
        ->assertOk();
});

it('returns not found when the version belongs to another document', function (): void {
    $secretariat = annotationActor(UserRole::Secretariat, 'mismatch');
    $document = Document::factory()->create(['author_id' => $secretariat->getKey()]);
    $other = Document::factory()->create(['author_id' => $secretariat->getKey()]);
    $version = annotationPdf($other, 'other-version');

    $this->actingAs($secretariat)
        ->putJson(route('documents.versions.annotations.update', [$document, $version]), [
            'payload' => [['annotation' => ['id' => 'mismatch', 'type' => 8]]],
        ])
        ->assertNotFound();
});

it('returns only the owner\'s annotations on GET', function (): void {
    $owner = annotationActor(UserRole::Secretariat, 'get-owner');
    $other = annotationActor(UserRole::Secretariat, 'get-other');
    $document = Document::factory()->create(['author_id' => $owner->getKey()]);
    $version = annotationPdf($document, 'get');

    DocumentAnnotation::factory()->create([
        'user_id' => $owner->getKey(),
        'document_version_id' => $version->getKey(),
        'payload' => [['annotation' => ['id' => 'owner-get', 'type' => 8]]],
    ]);

    DocumentAnnotation::factory()->create([
        'user_id' => $other->getKey(),
        'document_version_id' => $version->getKey(),
        'payload' => [['annotation' => ['id' => 'other-get', 'type' => 8]]],
    ]);

    $this->actingAs($owner)
        ->getJson(route('documents.versions.annotations.show', [$document, $version]))
        ->assertOk()
        ->assertJsonPath('payload.0.annotation.id', 'owner-get')
        ->assertJsonCount(1, 'payload');

    $this->actingAs($other)
        ->getJson(route('documents.versions.annotations.show', [$document, $version]))
        ->assertOk()
        ->assertJsonPath('payload.0.annotation.id', 'other-get')
        ->assertJsonCount(1, 'payload');
});

it('returns an empty payload when the viewer has no annotations yet', function (): void {
    $user = annotationActor(UserRole::Secretariat, 'get-empty');
    $document = Document::factory()->create(['author_id' => $user->getKey()]);
    $version = annotationPdf($document, 'empty');

    $this->actingAs($user)
        ->getJson(route('documents.versions.annotations.show', [$document, $version]))
        ->assertOk()
        ->assertJson(['payload' => []]);
});

it('forbids annotation GET without a download grant', function (): void {
    $member = annotationActor(UserRole::BoardMember, 'get-nogrant');
    $author = annotationActor(UserRole::Secretariat, 'get-grant-author');
    $document = Document::factory()->confidential()->create(['author_id' => $author->getKey()]);
    $version = annotationPdf($document, 'get-secret');

    $this->actingAs($member)
        ->getJson(route('documents.versions.annotations.show', [$document, $version]))
        ->assertForbidden();

    DocumentGrant::factory()->create([
        'document_id' => $document->getKey(),
        'user_id' => $member->getKey(),
        'role_id' => null,
        'committee_id' => null,
        'granted_by' => $author->getKey(),
        'granted_at' => now(),
        'ability' => 'download',
    ]);

    $this->actingAs($member)
        ->getJson(route('documents.versions.annotations.show', [$document, $version]))
        ->assertOk()
        ->assertJson(['payload' => []]);
});
