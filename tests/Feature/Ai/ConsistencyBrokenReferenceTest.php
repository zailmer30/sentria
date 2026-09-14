<?php

use App\Contracts\AI\ConsistencyCheckService;
use App\Enums\Confidentiality;
use App\Enums\UserRole;
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

function consistencyActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-consistency@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function consistencyDocument(User $author, string $text): Document
{
    $document = Document::factory()->create([
        'title' => 'Broken Reference Demo',
        'author_id' => $author->getKey(),
        'confidentiality' => Confidentiality::Internal,
    ]);

    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/broken-reference.txt',
        'original_filename' => 'broken-reference.txt',
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

    return $document->refresh();
}

it('surfaces a labeled broken cross-reference finding on a fixture document', function (): void {
    $reviewer = consistencyActor(UserRole::LegalTechnicalReviewer);
    $text = file_get_contents(base_path('tests/Fixtures/documents/broken_reference.txt'));

    expect($text)->not->toBeFalse();

    $document = consistencyDocument($reviewer, (string) $text);

    $response = $this->actingAs($reviewer)
        ->postJson(route('documents.consistency', $document))
        ->assertOk();

    expect($response->json('disclaimer'))->toBe('AI-assisted review. Human verification required.')
        ->and($response->json('is_ai_assisted'))->toBeTrue();

    $broken = collect($response->json('findings'))
        ->firstWhere('type', 'broken_cross_reference');

    expect($broken)->not->toBeNull()
        ->and($broken['message'])->toContain('Section 12')
        ->and($broken['severity'])->toBe('high');

    $serviceResult = app(ConsistencyCheckService::class)->check($reviewer, $document);

    expect(collect($serviceResult->findings)->contains(
        fn ($finding) => $finding->type === 'broken_cross_reference',
    ))->toBeTrue()
        ->and(AuditLog::query()->where('event', 'ai.consistency_check')->exists())->toBeTrue();
});

it('forbids consistency checks for users without permission', function (): void {
    $member = consistencyActor(UserRole::BoardMember);
    $text = file_get_contents(base_path('tests/Fixtures/documents/broken_reference.txt'));
    $document = consistencyDocument($member, (string) $text);

    $this->actingAs($member)
        ->postJson(route('documents.consistency', $document))
        ->assertForbidden();
});
