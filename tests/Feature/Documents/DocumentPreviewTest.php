<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\DocumentVersion;
use App\Models\PrivateNote;
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

function previewActor(UserRole $role, string $suffix = 'preview'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function previewPdf(Document $document, array $overrides = []): DocumentVersion
{
    $path = $overrides['file_path'] ?? 'documents/preview/'.$document->getKey().'.pdf';

    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");

    return DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'filing.pdf',
        'mime_type' => 'application/pdf',
        'scan_status' => 'skipped',
        ...$overrides,
    ]);
}

it('lets secretariat preview a pdf inline without treating it as a download', function (): void {
    $secretariat = previewActor(UserRole::Secretariat);
    $document = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal->value,
    ]);
    $version = previewPdf($document);

    $response = $this->actingAs($secretariat)
        ->get(route('documents.versions.preview', [$document, $version]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store')
        ->and($response->headers->get('Content-Disposition'))->toContain('inline')
        ->and(AuditLog::query()->where('event', 'document.preview')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'document.download')->exists())->toBeFalse();
});

it('forbids preview of a confidential document without a download grant', function (): void {
    $member = previewActor(UserRole::BoardMember, 'nogrant');
    $author = previewActor(UserRole::Secretariat, 'author');
    $document = Document::factory()->confidential()->create(['author_id' => $author->getKey()]);
    $version = previewPdf($document);

    $this->actingAs($member)
        ->get(route('documents.versions.preview', [$document, $version]))
        ->assertForbidden();
});

it('allows preview of a confidential document after an explicit grant', function (): void {
    $member = previewActor(UserRole::BoardMember, 'granted');
    $author = previewActor(UserRole::Secretariat, 'grantor');
    $document = Document::factory()->confidential()->create(['author_id' => $author->getKey()]);
    $version = previewPdf($document);

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
        ->get(route('documents.versions.preview', [$document, $version]))
        ->assertOk();
});

it('forbids preview when the version is not safe to serve', function (): void {
    $secretariat = previewActor(UserRole::Secretariat, 'infected');
    $document = Document::factory()->create(['author_id' => $secretariat->getKey()]);
    $version = previewPdf($document, [
        'scan_status' => 'infected',
        'file_path' => 'documents/preview/infected.pdf',
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.versions.preview', [$document, $version]))
        ->assertForbidden();
});

it('returns not found when the version belongs to another document', function (): void {
    $secretariat = previewActor(UserRole::Secretariat, 'mismatch');
    $document = Document::factory()->create(['author_id' => $secretariat->getKey()]);
    $other = Document::factory()->create(['author_id' => $secretariat->getKey()]);
    $version = previewPdf($other, ['file_path' => 'documents/preview/other.pdf']);

    $this->actingAs($secretariat)
        ->get(route('documents.versions.preview', [$document, $version]))
        ->assertNotFound();
});

it('rejects preview of a non-pdf version', function (): void {
    $secretariat = previewActor(UserRole::Secretariat, 'txt');
    $document = Document::factory()->create(['author_id' => $secretariat->getKey()]);
    $path = 'documents/preview/notes.txt';
    Storage::disk('local')->put($path, 'not a pdf');

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'notes.txt',
        'mime_type' => 'text/plain',
        'scan_status' => 'skipped',
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.versions.preview', [$document, $version]))
        ->assertUnprocessable();
});

it('includes only the viewer\'s private notes on the document view page', function (): void {
    $owner = previewActor(UserRole::Secretariat, 'notes-owner');
    $other = previewActor(UserRole::Secretariat, 'notes-other');
    $document = Document::factory()->create(['author_id' => $owner->getKey()]);
    $version = previewPdf($document);

    PrivateNote::factory()->create([
        'user_id' => $owner->getKey(),
        'notable_type' => Document::class,
        'notable_id' => $document->getKey(),
        'body' => 'Owner review note',
    ]);

    PrivateNote::factory()->create([
        'user_id' => $other->getKey(),
        'notable_type' => Document::class,
        'notable_id' => $document->getKey(),
        'body' => 'Someone else note',
    ]);

    $this->actingAs($owner)
        ->get(route('documents.versions.view', [$document, $version]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/View')
            ->has('privateNotes', 1)
            ->where('privateNotes.0.body', 'Owner review note')
            ->where('document.id', $document->getKey())
            ->where('annotations', []));
});

it('forbids the document view page without a download grant', function (): void {
    $member = previewActor(UserRole::BoardMember, 'view-nogrant');
    $author = previewActor(UserRole::Secretariat, 'view-author');
    $document = Document::factory()->confidential()->create(['author_id' => $author->getKey()]);
    $version = previewPdf($document, ['file_path' => 'documents/preview/view-secret.pdf']);

    $this->actingAs($member)
        ->get(route('documents.versions.view', [$document, $version]))
        ->assertForbidden();
});
