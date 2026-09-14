<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\AgendaItem;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentMetadata;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Session\Draft;
use App\States\Session\InSession;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    Storage::fake('local');
    config(['sentria.ai.api_key' => null]);
});

function assistantActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-assistant@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

function assistantDocument(User $author, Confidentiality $confidentiality, string $title = 'Assistant Agenda Measure'): Document
{
    $document = Document::factory()->create([
        'title' => $title,
        'abstract' => 'Provincial health appropriations and program implementation.',
        'author_id' => $author->getKey(),
        'confidentiality' => $confidentiality,
    ]);

    $text = "SECTION 1. Short Title.\nThis measure shall be known as the {$title}.\n\nSECTION 2. Appropriations.\nPHP 2,500,000 is authorized for the Provincial Health Office.";

    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/assistant.txt',
        'original_filename' => 'assistant.txt',
        'mime_type' => 'text/plain',
        'file_size' => strlen($text),
        'checksum_sha256' => hash('sha256', $text),
        'scan_status' => 'skipped',
        'ocr_status' => 'not_required',
        'text_extracted_at' => now(),
        'processing_status' => 'pending',
        'uploaded_by' => $author->getKey(),
    ]);

    Storage::disk('local')->put($version->file_path, $text);
    storeExtractedText($version, $text);
    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    DocumentMetadata::query()->create([
        'document_id' => $document->getKey(),
        'key' => 'ai_summary',
        'value' => 'Stored assistant summary for testing.',
        'value_json' => [
            'executive_summary' => 'Stored assistant summary for testing.',
            'purpose' => 'Support provincial health programs.',
            'key_provisions' => ['Appropriates PHP 2,500,000'],
            'important_dates' => [],
            'financial_info' => 'PHP 2,500,000',
            'affected_offices' => ['Provincial Health Office'],
            'related_docs_hints' => [],
            'potential_issues' => [],
            'model' => 'stored',
            'generated_at' => now()->toIso8601String(),
        ],
        'source' => 'ai',
    ]);

    return $document->refresh();
}

/**
 * @return array{secretariat: User, member: User, session: LegislativeSession, item: AgendaItem, document: Document}
 */
function assistantInSessionWithDocument(): array
{
    $secretariat = assistantActor(UserRole::Secretariat);
    $member = assistantActor(UserRole::BoardMember);

    $session = LegislativeSession::factory()->create([
        'session_number' => 'AS-401',
        'title' => 'Assistant Session',
        'seated_member_count' => 12,
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $document = assistantDocument($secretariat, Confidentiality::Internal, 'Visible Assistant Measure');

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'item_number' => '1',
        'title' => 'First Reading — Visible Assistant Measure',
    ]);

    return compact('secretariat', 'member', 'session', 'item', 'document');
}

it('returns assistant context for an in-session board member with ai.use on a visible agenda document', function (): void {
    ['secretariat' => $secretariat, 'member' => $member, 'session' => $session, 'document' => $document] = assistantInSessionWithDocument();

    $response = $this->actingAs($member)
        ->getJson(route('sessions.assistant.show', $session))
        ->assertOk();

    expect($response->json('available'))->toBeTrue()
        ->and($response->json('current_agenda_item.document.id'))->toBe($document->getKey())
        ->and($response->json('document_restricted'))->toBeFalse()
        ->and($response->json('summary.executive_summary'))->toBe('Stored assistant summary for testing.')
        ->and($response->json('document_history'))->not->toBeEmpty()
        ->and($response->json('document_history.0.uploaded_by'))->toBe($secretariat->display_name);

    $floor = $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk();

    $floor->assertInertia(fn ($page) => $page
        ->component('Sessions/Floor/BoardMember')
        ->where('assistant.available', true)
        ->where('assistant.summary.executive_summary', 'Stored assistant summary for testing.')
        ->where('assistant.document_history.0.uploaded_by', $secretariat->display_name)
        ->where('can.use_assistant', true)
        ->has('reading_pack'));

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('assistant.available', true)
            ->where('assistant.document_history.0.uploaded_by', $secretariat->display_name));
});

it('withholds summary and extracted content for confidential agenda documents without grants', function (): void {
    ['secretariat' => $secretariat, 'member' => $member, 'session' => $session] = assistantInSessionWithDocument();

    $confidential = assistantDocument($secretariat, Confidentiality::Confidential, 'Confidential Assistant Measure');

    AgendaItem::query()
        ->where('session_id', $session->getKey())
        ->update(['document_id' => $confidential->getKey()]);

    $response = $this->actingAs($member)
        ->getJson(route('sessions.assistant.show', $session))
        ->assertOk();

    expect($response->json('document_restricted'))->toBeTrue()
        ->and($response->json('summary'))->toBeNull()
        ->and($response->json('related_legislation'))->toBe([])
        ->and($response->json('previous_similar'))->toBe([])
        ->and($response->json('document_history'))->toBe([]);

    $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('Stored assistant summary for testing.')
        ->and($encoded)->not->toContain('SECTION 2. Appropriations.');
});

it('rejects the assistant endpoint when the session is still in draft', function (): void {
    ['member' => $member, 'session' => $session] = assistantInSessionWithDocument();

    $session->update(['status' => Draft::$name, 'actual_start_at' => null]);

    $this->actingAs($member)
        ->getJson(route('sessions.assistant.show', $session))
        ->assertStatus(422);

    $this->actingAs($member)
        ->postJson(route('sessions.assistant.search', $session), ['query' => 'health appropriations'])
        ->assertStatus(422);
});

it('forbids public users and users without ai.use', function (): void {
    ['session' => $session] = assistantInSessionWithDocument();

    $public = assistantActor(UserRole::PublicUser);

    $this->actingAs($public)
        ->getJson(route('sessions.assistant.show', $session))
        ->assertForbidden();

    $memberWithoutAi = User::factory()->create([
        'email' => 'board-member-no-ai-assistant@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ]);
    $memberWithoutAi->givePermissionTo('sessions.view');

    $this->actingAs($memberWithoutAi)
        ->getJson(route('sessions.assistant.show', $session))
        ->assertForbidden();
});

it('audits session assistant search requests', function (): void {
    ['member' => $member, 'session' => $session] = assistantInSessionWithDocument();

    $this->actingAs($member)
        ->postJson(route('sessions.assistant.search', $session), ['query' => 'health appropriations'])
        ->assertOk();

    expect(AuditLog::query()->where('event', 'ai.session_assistant.search')->exists())->toBeTrue();
});
