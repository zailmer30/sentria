<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentMetadata;
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
    config(['sentria.ai.api_key' => null]);
});

function summaryActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-ai-sum@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function summaryDocument(User $author): Document
{
    $document = Document::factory()->create([
        'title' => 'Summary Target Ordinance',
        'author_id' => $author->getKey(),
        'confidentiality' => Confidentiality::Internal,
    ]);

    $text = "SECTION 1. Short Title.\nThis measure shall be known as the Summary Demo Ordinance.\n\nSECTION 2. Appropriations.\nPHP 1,000,000 is authorized for the Provincial Health Office.";

    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/summary.txt',
        'original_filename' => 'summary.txt',
        'mime_type' => 'text/plain',
        'file_size' => strlen($text),
        'checksum_sha256' => hash('sha256', $text),
        'scan_status' => 'skipped',
        'ocr_status' => 'not_required',
        'text_extracted_at' => now(),
        'processing_status' => 'pending',
        'uploaded_by' => $author->getKey(),
    ]);

    storeExtractedText($version, $text);
    Storage::disk('local')->put($version->file_path, $text);
    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    return $document->refresh();
}

it('generates a structured summary for authorized users', function (): void {
    $secretariat = summaryActor(UserRole::Secretariat);
    $document = summaryDocument($secretariat);

    $response = $this->actingAs($secretariat)
        ->postJson(route('documents.summary', $document))
        ->assertOk();

    $summary = $response->json('summary');

    expect($summary['executive_summary'])->not->toBeEmpty()
        ->and($summary['purpose'])->not->toBeEmpty()
        ->and($summary['key_provisions'])->not->toBeEmpty()
        ->and(DocumentMetadata::query()->where('document_id', $document->getKey())->where('key', 'ai_summary')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'ai.summarize')->exists())->toBeTrue();

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Documents/Show')
            ->has('aiSummary.executive_summary')
            ->where('can.summarize', true));
});

it('forbids summary generation for users without document access', function (): void {
    $secretariat = summaryActor(UserRole::Secretariat);
    $member = summaryActor(UserRole::BoardMember);
    $document = Document::factory()->confidential()->create([
        'title' => 'Secret Summary Doc',
        'author_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($member)
        ->postJson(route('documents.summary', $document))
        ->assertForbidden();
});
