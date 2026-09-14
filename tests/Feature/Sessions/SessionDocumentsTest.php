<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    Storage::fake('local');
});

function sessionDocumentsActor(UserRole $role, string $suffix = 'docs'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function sessionDocumentsPdf(Document $document, string $suffix): DocumentVersion
{
    $path = "documents/session-docs/{$suffix}.pdf";

    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");

    return DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'measure.pdf',
        'mime_type' => 'application/pdf',
        'scan_status' => 'skipped',
        'is_current' => true,
    ]);
}

it('counts attached agenda documents on the sitting register', function (): void {
    $secretariat = sessionDocumentsActor(UserRole::Secretariat, 'index');
    $session = LegislativeSession::factory()->create();
    $document = Document::factory()->create();

    AgendaItem::factory()->procedural('new-business', 'Bound measure', 1)->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
    ]);
    AgendaItem::factory()->procedural('privilege-hour', 'Privilege Hour', 2)->create([
        'session_id' => $session->getKey(),
        'document_id' => null,
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Index')
            ->where('sessions.data.0.attached_document_count', 1));
});

it('lists unique documents bound to a sitting for preview', function (): void {
    $secretariat = sessionDocumentsActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create();
    $document = Document::factory()->create(['title' => 'BAC Resolution']);
    $version = sessionDocumentsPdf($document, 'bac');

    AgendaItem::factory()->procedural('first-reading', 'BAC Resolution', 1)->create([
        'session_id' => $session->getKey(),
        'item_number' => '7.1',
        'document_id' => $document->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.documents.index', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Documents')
            ->where('session.id', $session->getKey())
            ->has('documents', 1)
            ->where('documents.0.slug', $document->slug)
            ->where('documents.0.item_number', '7.1')
            ->where('documents.0.can_preview', true)
            ->where(
                'documents.0.preview_url',
                route('documents.versions.preview', [$document, $version]),
            ));
});

it('hides confidential sitting documents from members without a grant', function (): void {
    $secretariat = sessionDocumentsActor(UserRole::Secretariat, 'author');
    $member = sessionDocumentsActor(UserRole::BoardMember, 'member');
    $session = LegislativeSession::factory()->create();

    $visible = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal,
        'title' => 'Open measure',
    ]);
    $secret = Document::factory()->confidential()->create([
        'author_id' => $secretariat->getKey(),
        'title' => 'Closed measure',
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'title' => 'Open measure',
        'document_id' => $visible->getKey(),
        'position' => 1,
    ]);
    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'title' => 'Closed measure',
        'document_id' => $secret->getKey(),
        'position' => 2,
    ]);

    $this->actingAs($member)
        ->get(route('sessions.documents.index', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Documents')
            ->has('documents', 1)
            ->where('documents.0.title', 'Open measure'));
});
