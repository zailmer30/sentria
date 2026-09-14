<?php

use App\Contracts\AI\RAGService;
use App\Enums\ProcessingStatus;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Support\AI\InsufficientEvidence;
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

function aiActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-ai-rag@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function aiEmbedConfidentialDocument(User $author, string $uniquePhrase): Document
{
    $document = Document::factory()->confidential()->create([
        'title' => 'Confidential RAG Isolation Measure',
        'author_id' => $author->getKey(),
    ]);

    $text = "SECTION 1. Restricted clause.\n{$uniquePhrase}\nFunds are confidential.";

    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/restricted.txt',
        'original_filename' => 'restricted.txt',
        'mime_type' => 'text/plain',
        'file_size' => strlen($text),
        'checksum_sha256' => hash('sha256', $text),
        'scan_status' => 'skipped',
        'ocr_status' => 'not_required',
        'text_extracted_at' => now(),
        'processing_status' => ProcessingStatus::Pending->value,
        'uploaded_by' => $author->getKey(),
    ]);

    storeExtractedText($version, $text);
    Storage::disk('local')->put($version->file_path, $text);
    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    return $document->refresh();
}

it('does not retrieve unauthorized confidential documents for rag queries', function (): void {
    $secretariat = aiActor(UserRole::Secretariat);
    $member = aiActor(UserRole::BoardMember);
    $uniquePhrase = 'ZXYQ-UNIQUE-CONFIDENTIAL-PHRASE-998877';

    $document = aiEmbedConfidentialDocument($secretariat, $uniquePhrase);

    $result = app(RAGService::class)->ask($member, "What does the record say about {$uniquePhrase}?");

    expect($result->insufficientEvidence)->toBeTrue()
        ->and($result->content)->toBe(InsufficientEvidence::PHRASE)
        ->and($result->citations)->toBeEmpty();

    $audit = AuditLog::query()->where('event', 'ai.rag.query')->latest('sequence')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->context['retrieved_document_ids'] ?? [])->not->toContain($document->getKey())
        ->and($audit->context['retrieved_document_ids'] ?? [])->toBe([]);
});

it('retrieves confidential documents after an explicit grant', function (): void {
    $secretariat = aiActor(UserRole::Secretariat);
    $member = aiActor(UserRole::BoardMember);
    $uniquePhrase = 'ZXYQ-GRANTED-CONFIDENTIAL-PHRASE-112233';

    $document = aiEmbedConfidentialDocument($secretariat, $uniquePhrase);

    DocumentGrant::factory()->create([
        'document_id' => $document->getKey(),
        'user_id' => $member->getKey(),
        'role_id' => null,
        'committee_id' => null,
        'granted_by' => $secretariat->getKey(),
        'granted_at' => now(),
    ]);

    $result = app(RAGService::class)->ask($member, "Summarize {$uniquePhrase}");

    expect($result->insufficientEvidence)->toBeFalse()
        ->and($result->citations)->not->toBeEmpty()
        ->and(collect($result->citations)->pluck('documentId'))->toContain($document->getKey());

    $audit = AuditLog::query()->where('event', 'ai.rag.query')->latest('sequence')->first();

    expect($audit->context['retrieved_document_ids'] ?? [])->toContain($document->getKey());
});
