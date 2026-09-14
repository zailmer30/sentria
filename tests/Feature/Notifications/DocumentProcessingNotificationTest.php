<?php

use App\Contracts\AI\EmbeddingService;
use App\Enums\Confidentiality;
use App\Enums\ProcessingStatus;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Notifications\DocumentProcessingCompleted;
use App\Notifications\DocumentProcessingFailed;
use App\Services\AI\HashEmbeddingService;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
    config(['sentria.ai.api_key' => null]);
});

function notifyIngestActor(UserRole $role, string $suffix = 'proc'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function makeProcessableVersion(User $uploader, ?User $author = null): DocumentVersion
{
    $author ??= $uploader;
    $document = Document::factory()->create([
        'author_id' => $author->getKey(),
        'confidentiality' => Confidentiality::Internal,
    ]);

    $body = "SECTION 1. Short Title.\nNotification pipeline text.\n\nSECTION 2. Appropriations.\nFunds authorized.";
    $path = 'documents/'.$document->getKey().'/notify.txt';
    Storage::disk('local')->put($path, $body);

    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'notify.txt',
        'mime_type' => 'text/plain',
        'file_size' => strlen($body),
        'checksum_sha256' => hash('sha256', $body),
        'scan_status' => 'skipped',
        'ocr_status' => 'pending',
        'processing_status' => ProcessingStatus::Pending->value,
        'uploaded_by' => $uploader->getKey(),
    ]);

    return $version;
}

it('notifies the uploader when document processing completes', function (): void {
    Notification::fake();

    $uploader = notifyIngestActor(UserRole::Secretariat);
    $author = notifyIngestActor(UserRole::BoardMember, 'author');
    $version = makeProcessableVersion($uploader, $author);

    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    expect($version->fresh()->processing_status)->toBe(ProcessingStatus::Completed);

    Notification::assertSentTo($uploader, DocumentProcessingCompleted::class);
    Notification::assertSentTo($author, DocumentProcessingCompleted::class);
    Notification::assertNotSentTo($uploader, DocumentProcessingFailed::class);
});

it('notifies the uploader when malware scan blocks processing', function (): void {
    Notification::fake();

    $uploader = notifyIngestActor(UserRole::Secretariat, 'malware');
    $version = makeProcessableVersion($uploader);
    $version->update(['scan_status' => 'infected']);

    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    expect($version->fresh()->processing_status)->toBe(ProcessingStatus::Failed);

    Notification::assertSentTo($uploader, DocumentProcessingFailed::class);
    Notification::assertNotSentTo($uploader, DocumentProcessingCompleted::class);
});

it('does not notify on intermediate retry failures', function (): void {
    Notification::fake();

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
                return 'flaky-notify-test';
            }
        };
    });

    $uploader = notifyIngestActor(UserRole::Secretariat, 'retry');
    $version = makeProcessableVersion($uploader);
    storeExtractedText($version, Storage::disk('local')->get($version->file_path));
    $version->update([
        'ocr_status' => 'completed',
        'text_extracted_at' => now(),
    ]);

    try {
        ProcessDocumentVersionJob::dispatchSync($version->getKey());
    } catch (RuntimeException) {
        // expected first failure — failed() is not invoked by dispatchSync
    }

    Notification::assertNothingSent();

    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    Notification::assertSentTo($uploader, DocumentProcessingCompleted::class);
    Notification::assertNotSentTo($uploader, DocumentProcessingFailed::class);
});

it('notifies once when the job permanently fails', function (): void {
    Notification::fake();

    $uploader = notifyIngestActor(UserRole::Secretariat, 'permfail');
    $version = makeProcessableVersion($uploader);
    $version->update([
        'processing_status' => ProcessingStatus::Failed->value,
        'processing_error' => 'Permanent failure.',
    ]);

    $job = new ProcessDocumentVersionJob($version->getKey());
    $job->failed(new RuntimeException('Permanent failure.'));

    Notification::assertSentToTimes($uploader, DocumentProcessingFailed::class, 1);
});
