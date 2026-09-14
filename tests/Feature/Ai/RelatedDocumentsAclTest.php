<?php

use App\Contracts\AI\RelatedDocumentService;
use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\AuditLog;
use App\Models\Document;
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

function relatedActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-related@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function relatedDocumentWithText(User $author, string $title, string $text, Confidentiality $confidentiality): Document
{
    $document = Document::factory()->create([
        'title' => $title,
        'abstract' => 'Shared appropriations and health program language for related document discovery.',
        'author_id' => $author->getKey(),
        'confidentiality' => $confidentiality,
    ]);

    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/related.txt',
        'original_filename' => 'related.txt',
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

    return $document->refresh();
}

it('never returns unauthorized confidential neighbors in related document suggestions', function (): void {
    $secretariat = relatedActor(UserRole::Secretariat);
    $member = relatedActor(UserRole::BoardMember);

    $sharedPhrase = 'SHARED-RELATED-PHRASE-ACL-445566 appropriations for provincial health programs';

    $source = relatedDocumentWithText(
        $secretariat,
        'Visible Source Measure',
        "SECTION 1. Short Title.\n{$sharedPhrase}\n\nSECTION 2. Appropriations.\nFunds are authorized.",
        Confidentiality::Internal,
    );

    $confidentialNeighbor = relatedDocumentWithText(
        $secretariat,
        'Confidential Neighbor Measure',
        "SECTION 1. Short Title.\n{$sharedPhrase}\n\nSECTION 2. Restricted Annex.\nConfidential implementation details.",
        Confidentiality::Confidential,
    );

    $response = $this->actingAs($member)
        ->getJson(route('documents.related', $source))
        ->assertOk();

    expect($response->json('is_ai_suggestion'))->toBeTrue();

    $returnedIds = collect($response->json('suggestions'))->pluck('document_id');

    expect($returnedIds)->not->toContain($confidentialNeighbor->getKey());

    $serviceResult = app(RelatedDocumentService::class)->findRelated($member, $source, 8);

    expect(collect($serviceResult->suggestions)->pluck('documentId'))
        ->not->toContain($confidentialNeighbor->getKey())
        ->and(AuditLog::query()->where('event', 'ai.related_documents')->exists())->toBeTrue();
});

it('forbids related document lookup without document access', function (): void {
    $secretariat = relatedActor(UserRole::Secretariat);
    $member = relatedActor(UserRole::BoardMember);

    $confidential = Document::factory()->confidential()->create([
        'title' => 'Hidden Related Target',
        'author_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($member)
        ->getJson(route('documents.related', $confidential))
        ->assertForbidden();
});
