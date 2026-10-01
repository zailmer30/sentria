<?php

use App\Contracts\AI\EmbeddingService;
use App\Contracts\AI\LegislativeSearchService;
use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\ProcessingStatus;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Jobs\Documents\RunOcrJob;
use App\Models\Document;
use App\Models\DocumentEmbedding;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AI\HashEmbeddingService;
use App\Services\Documents\DocumentTextStore;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
    config(['sentria.ai.api_key' => null]);
});

function ingestActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-ingest@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function ingestUpload(string $name, string $contents, string $mime = 'text/plain'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents, $mime);
}

it('dispatches the ingest pipeline after upload', function (): void {
    Queue::fake();

    $secretariat = ingestActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'Queued Ingest Ordinance',
            'document_type' => DocumentType::ProposedOrdinance->value,
            'confidentiality' => Confidentiality::Internal->value,
            'reference_number' => 'MO-2026-'.fake()->unique()->numerify('####'),
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'external_author' => 'Maria Santos',
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => ingestUpload('queued.txt', "SECTION 1. Short Title.\nDemo text."),
        ])
        ->assertRedirect();

    Queue::assertPushed(ProcessDocumentVersionJob::class, 1);
});

it('processes uploaded text through the ingest pipeline and makes chunks searchable', function (): void {
    $secretariat = ingestActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'Ingest Pipeline Ordinance',
            'document_type' => DocumentType::ProposedOrdinance->value,
            'confidentiality' => Confidentiality::Internal->value,
            'reference_number' => 'MO-2026-'.fake()->unique()->numerify('####'),
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'external_author' => 'Maria Santos',
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => ingestUpload('ordinance.txt', "SECTION 1. Short Title.\nThis measure shall be known as the Demo Ordinance.\n\nSECTION 2. Appropriations.\nFunds are hereby authorized."),
        ])
        ->assertRedirect();

    $document = Document::query()->where('title', 'Ingest Pipeline Ordinance')->firstOrFail();
    $version = DocumentVersion::query()->where('document_id', $document->getKey())->firstOrFail();

    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    $version->refresh();
    expect($version->processing_status)->toBe(ProcessingStatus::Completed)
        ->and($version->processed_at)->not->toBeNull()
        ->and(DocumentEmbedding::query()->where('document_version_id', $version->getKey())->count())->toBeGreaterThan(0);

    $search = app(LegislativeSearchService::class)->search($secretariat, 'Appropriations', 5);
    expect($search->hits)->not->toBeEmpty()
        ->and(collect($search->hits)->contains(fn ($hit) => str_contains($hit->chunkText, 'Appropriations')))->toBeTrue();
});

it('fills extracted text for scanned uploads without changing the file checksum', function (): void {
    $secretariat = ingestActor(UserRole::Secretariat);

    $document = Document::factory()->create([
        'title' => 'Scanned Image Measure',
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal,
    ]);

    $relativePath = 'documents/'.$document->getKey().'/scanned.png';
    Storage::disk('local')->put($relativePath, 'fake-image-binary-content');
    $checksum = hash('sha256', 'fake-image-binary-content');

    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => $relativePath,
        'original_filename' => 'scanned-measure.png',
        'mime_type' => 'image/png',
        'file_size' => strlen('fake-image-binary-content'),
        'checksum_sha256' => $checksum,
        'scan_status' => 'skipped',
        'scanned_at' => now(),
        'scanner' => 'null',
        'ocr_status' => 'pending',
        'processing_status' => ProcessingStatus::Pending->value,
        'uploaded_by' => $secretariat->getKey(),
    ]);

    RunOcrJob::dispatchSync($version->getKey());

    $version->refresh();
    expect(app(DocumentTextStore::class)->get($version))->not->toBeNull()
        ->and($version->ocr_status)->toBe('completed')
        ->and(hash_file('sha256', Storage::disk('local')->path($relativePath)))->toBe($checksum);
});

it('stores embeddings with the configured vector dimensions', function (): void {
    config(['sentria.ai.embedding_dimensions' => 1536]);

    $secretariat = ingestActor(UserRole::Secretariat);
    $document = Document::factory()->create(['author_id' => $secretariat->getKey()]);
    $body = "SECTION 1. Demo text for chunk embedding.\n".str_repeat('Appropriations language for vector sizing. ', 120);
    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/sample.txt',
        'original_filename' => 'sample.txt',
        'mime_type' => 'text/plain',
        'file_size' => strlen($body),
        'checksum_sha256' => hash('sha256', $body),
        'scan_status' => 'skipped',
        'ocr_status' => 'completed',
        'text_extracted_at' => now(),
        'processing_status' => ProcessingStatus::Pending->value,
        'uploaded_by' => $secretariat->getKey(),
    ]);

    storeExtractedText($version, $body);
    Storage::disk('local')->put($version->file_path, $body);

    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    $embedding = DocumentEmbedding::query()->where('document_version_id', $version->getKey())->firstOrFail();
    $vector = app(HashEmbeddingService::class)->vectorForText($embedding->chunk_text);

    expect(count($vector))->toBe(1536)
        ->and(app(EmbeddingService::class)->dimensions())->toBe(1536);
});

it('marks processing failed and succeeds on retry after a transient embedding failure', function (): void {
    $attempts = 0;

    $this->app->bind(EmbeddingService::class, function () use (&$attempts): EmbeddingService {
        return new class($attempts) implements EmbeddingService
        {
            public function __construct(private int &$attempts) {}

            public function embed(array $texts): array
            {
                $this->attempts++;

                if ($this->attempts === 1) {
                    throw new RuntimeException('Simulated embedding provider outage.');
                }

                return app(HashEmbeddingService::class)->embed($texts);
            }

            public function dimensions(): int
            {
                return app(HashEmbeddingService::class)->dimensions();
            }

            public function modelName(): string
            {
                return 'flaky-test';
            }
        };
    });

    $secretariat = ingestActor(UserRole::Secretariat);
    $document = Document::factory()->create(['author_id' => $secretariat->getKey()]);
    $retryBody = "SECTION 1. Retry test.\nFunds for testing only.";
    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/retry.txt',
        'original_filename' => 'retry.txt',
        'mime_type' => 'text/plain',
        'file_size' => strlen($retryBody),
        'checksum_sha256' => hash('sha256', $retryBody),
        'scan_status' => 'skipped',
        'ocr_status' => 'completed',
        'text_extracted_at' => now(),
        'processing_status' => ProcessingStatus::Pending->value,
        'uploaded_by' => $secretariat->getKey(),
    ]);

    storeExtractedText($version, $retryBody);
    Storage::disk('local')->put($version->file_path, $retryBody);

    try {
        ProcessDocumentVersionJob::dispatchSync($version->getKey());
    } catch (RuntimeException) {
        // expected first failure
    }

    $version->refresh();
    expect($version->processing_status)->toBe(ProcessingStatus::Failed)
        ->and($version->processing_error)->toContain('Simulated embedding provider outage');

    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    $version->refresh();
    expect($version->processing_status)->toBe(ProcessingStatus::Completed)
        ->and(DocumentEmbedding::query()->where('document_version_id', $version->getKey())->count())->toBeGreaterThan(0);
});

it('has an HNSW index on document_embeddings.embedding', function (): void {
    $indexes = DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'document_embeddings'");

    $match = collect($indexes)->first(
        fn ($index) => str_contains(strtolower($index->indexdef), 'hnsw')
            && str_contains(strtolower($index->indexdef), 'embedding'),
    );

    expect($match)->not->toBeNull();
});

it('does not return unauthorized chunks from legislative search', function (): void {
    $secretariat = ingestActor(UserRole::Secretariat);
    $member = ingestActor(UserRole::BoardMember);

    $restricted = Document::factory()->create([
        'title' => 'Restricted Search Doc',
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Confidential,
    ]);

    $restrictedBody = 'SECTION 1. Confidential appropriations for restricted access only.';
    $version = DocumentVersion::query()->create([
        'document_id' => $restricted->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$restricted->getKey().'/restricted.txt',
        'original_filename' => 'restricted.txt',
        'mime_type' => 'text/plain',
        'file_size' => strlen($restrictedBody),
        'checksum_sha256' => hash('sha256', $restrictedBody),
        'scan_status' => 'skipped',
        'ocr_status' => 'completed',
        'text_extracted_at' => now(),
        'processing_status' => ProcessingStatus::Pending->value,
        'uploaded_by' => $secretariat->getKey(),
    ]);

    storeExtractedText($version, $restrictedBody);
    Storage::disk('local')->put($version->file_path, $restrictedBody);
    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    $results = app(LegislativeSearchService::class)->search($member, 'appropriations', 8);

    expect(collect($results->hits)->pluck('documentId'))->not->toContain($restricted->getKey());
});
