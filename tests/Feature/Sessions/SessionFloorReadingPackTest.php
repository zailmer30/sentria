<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Session\InSession;
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

function readingPackActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-pack-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

function readingPackPdf(Document $document, string $suffix): DocumentVersion
{
    $path = "documents/reading-pack/{$suffix}.pdf";

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

it('includes an ACL-filtered reading_pack on the member floor', function (): void {
    $secretariat = readingPackActor(UserRole::Secretariat, 'sec');
    $member = readingPackActor(UserRole::BoardMember, 'member');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
        'seated_member_count' => 12,
    ]);

    $visible = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal,
        'title' => 'Visible Floor Measure',
    ]);
    $visibleVersion = readingPackPdf($visible, 'visible');

    $secret = Document::factory()->confidential()->create([
        'author_id' => $secretariat->getKey(),
        'title' => 'Secret Floor Measure',
    ]);
    readingPackPdf($secret, 'secret');

    $current = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $visible->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'item_number' => '1',
        'title' => 'Visible item',
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $secret->getKey(),
        'status' => 'pending',
        'position' => 2,
        'item_number' => '2',
        'title' => 'Secret item',
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => null,
        'status' => 'pending',
        'position' => 3,
        'item_number' => '3',
        'title' => 'No document',
    ]);

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/BoardMember')
            ->has('reading_pack', 3)
            ->where('reading_pack.0.id', $current->getKey())
            ->where('reading_pack.0.document.id', $visible->getKey())
            ->where('reading_pack.0.document.can_preview', true)
            ->where('reading_pack.0.document.version_id', $visibleVersion->getKey())
            ->where('reading_pack.0.document.preview_url', route('documents.versions.preview', [$visible, $visibleVersion]))
            ->where('reading_pack.0.document.reference_number', $visible->reference_number)
            ->where('reading_pack.0.document.author', $secretariat->display_name)
            ->where('reading_pack.0.document.abstract', $visible->abstract)
            ->where('reading_pack.1.document', null)
            ->where('reading_pack.2.document', null));
});

it('exposes confidential documents in reading_pack only after a download grant', function (): void {
    $secretariat = readingPackActor(UserRole::Secretariat, 'grant-sec');
    $member = readingPackActor(UserRole::BoardMember, 'grant-member');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $secret = Document::factory()->confidential()->create([
        'author_id' => $secretariat->getKey(),
        'title' => 'Granted Secret',
    ]);
    $version = readingPackPdf($secret, 'granted');

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $secret->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
    ]);

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reading_pack.0.document', null));

    DocumentGrant::factory()->create([
        'document_id' => $secret->getKey(),
        'user_id' => $member->getKey(),
        'role_id' => null,
        'committee_id' => null,
        'granted_by' => $secretariat->getKey(),
        'granted_at' => now(),
        'ability' => 'download',
    ]);

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reading_pack.0.document.id', $secret->getKey())
            ->where('reading_pack.0.document.can_preview', true)
            ->where('reading_pack.0.document.version_id', $version->getKey()));
});

it('includes the first-reading measure and preview in the reading pack', function (): void {
    $secretariat = readingPackActor(UserRole::Secretariat, 'title-sec');
    $member = readingPackActor(UserRole::BoardMember, 'title-member');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
        'seated_member_count' => 12,
    ]);

    $measure = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal,
        'title' => 'First Reading By Title',
        'abstract' => 'The ordinance body is on the floor for reference.',
    ]);
    readingPackPdf($measure, 'first-read');

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $measure->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'reading_number' => 1,
        'title' => 'First reading',
    ]);

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reading_pack.0.title_only', true)
            ->where('reading_pack.0.reading_number', 1)
            ->where('reading_pack.0.document.title', 'First Reading By Title')
            ->where('reading_pack.0.document.can_preview', true)
            ->has('reading_pack.0.document.preview_url')
            ->where('reading_pack.0.document.abstract', 'The ordinance body is on the floor for reference.'));
});
