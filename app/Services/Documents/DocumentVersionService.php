<?php

namespace App\Services\Documents;

use App\Contracts\Malware\MalwareScanner;
use App\Enums\Confidentiality;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentType;
use App\Enums\ProcessingStatus;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\States\Document\Archive;
use App\States\Document\Submitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DocumentVersionService
{
    public function __construct(
        private readonly MalwareScanner $scanner,
        private readonly AuditLogger $audit,
        private readonly DocumentReferenceAllocator $references,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     document_type: string,
     *     confidentiality?: string,
     *     abstract?: string|null,
     *     external_author?: string|null,
     *     enacting_clause?: string|null,
     *     explanatory_note?: string|null,
     *     committee_id?: string|null,
     *     session_id?: string|null,
     *     reference_number?: string|null,
     *     tags?: list<string>|null,
     *     origin?: string,
     *     status?: string,
     *     archived_at?: Carbon|null,
     *     reference_year?: int|null,
     *     ingest_after_response?: bool,
     * }  $meta
     */
    public function createWithUpload(
        User $user,
        array $meta,
        UploadedFile $file,
        ?string $changeSummary = null,
    ): Document {
        $this->assertAllowedMime($file);

        return DB::transaction(function () use ($user, $meta, $file, $changeSummary): Document {
            $document = Document::query()->create([
                'title' => $meta['title'],
                'slug' => $this->uniqueSlug($meta['title']),
                'abstract' => $meta['abstract'] ?? null,
                'external_author' => $meta['external_author'] ?? null,
                'enacting_clause' => $meta['enacting_clause'] ?? null,
                'explanatory_note' => $meta['explanatory_note'] ?? null,
                'document_type' => $meta['document_type'],
                'status' => $meta['status'] ?? Submitted::$name,
                'origin' => $meta['origin'] ?? DocumentOrigin::Member->value,
                'confidentiality' => $meta['confidentiality'] ?? Confidentiality::Internal->value,
                'author_id' => $user->getKey(),
                'committee_id' => $meta['committee_id'] ?? null,
                'session_id' => $meta['session_id'] ?? null,
                'reference_number' => $this->references->allocate(
                    DocumentType::from($meta['document_type']),
                    $meta['reference_year'] ?? null,
                ),
                'submitted_at' => now(),
                'archived_at' => $meta['archived_at'] ?? null,
                'tags' => $meta['tags'] ?? null,
                'version_count' => 0,
            ]);

            $this->storeVersion(
                $document,
                $user,
                $file,
                1,
                $changeSummary,
                markCurrent: true,
                ingestAfterResponse: (bool) ($meta['ingest_after_response'] ?? false),
            );

            $this->audit->record(
                event: 'document.upload',
                category: 'documents',
                auditable: $document,
                actor: $user,
                new: ['version' => 1, 'filename' => $file->getClientOriginalName()],
                message: 'Initial document version uploaded.',
            );

            return $document->refresh()->load(['currentVersion', 'author']);
        });
    }

    /**
     * Historical backfile: already enacted/adopted, never walked through IRP.
     */
    public function createBackfile(
        User $user,
        array $meta,
        UploadedFile $file,
        ?string $changeSummary = null,
    ): Document {
        return $this->createWithUpload(
            $user,
            [
                ...$meta,
                'origin' => DocumentOrigin::Archive->value,
                'status' => Archive::$name,
                'archived_at' => now(),
                'ingest_after_response' => true,
            ],
            $file,
            $changeSummary,
        );
    }

    public function uploadNewVersion(
        Document $document,
        User $user,
        UploadedFile $file,
        ?string $changeSummary = null,
        bool $rejectUnsafeScan = false,
    ): DocumentVersion {
        $this->assertAllowedMime($file);

        return DB::transaction(function () use ($document, $user, $file, $changeSummary, $rejectUnsafeScan): DocumentVersion {
            $nextNumber = ($document->version_count ?? 0) + 1;

            DocumentVersion::query()
                ->where('document_id', $document->getKey())
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $version = $this->storeVersion(
                $document,
                $user,
                $file,
                $nextNumber,
                $changeSummary,
                markCurrent: true,
                rejectUnsafeScan: $rejectUnsafeScan,
            );

            $this->audit->record(
                event: 'document.upload',
                category: 'documents',
                auditable: $document,
                actor: $user,
                new: [
                    'version' => $nextNumber,
                    'filename' => $file->getClientOriginalName(),
                ],
                message: 'New document version uploaded.',
            );

            return $version;
        });
    }

    private function storeVersion(
        Document $document,
        User $user,
        UploadedFile $file,
        int $versionNumber,
        ?string $changeSummary,
        bool $markCurrent,
        bool $ingestAfterResponse = false,
        bool $rejectUnsafeScan = false,
    ): DocumentVersion {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $hash = hash_file('sha256', $file->getRealPath() ?: '') ?: hash('sha256', (string) microtime(true));
        $relativePath = sprintf(
            'documents/%s/v%d-%s.%s',
            $document->getKey(),
            $versionNumber,
            substr($hash, 0, 16),
            $extension,
        );

        Storage::disk('local')->putFileAs(
            dirname($relativePath),
            $file,
            basename($relativePath),
        );

        $absolutePath = Storage::disk('local')->path($relativePath);
        $scan = $this->scanner->scan($absolutePath);

        if ($rejectUnsafeScan && ! $scan->isSafeToServe()) {
            Storage::disk('local')->delete($relativePath);

            throw ValidationException::withMessages([
                'file' => 'The signed copy did not pass the security scan and was not attached.',
            ]);
        }

        $version = DocumentVersion::query()->create([
            'document_id' => $document->getKey(),
            'version_number' => $versionNumber,
            'is_current' => $markCurrent,
            'disk' => 'local',
            'file_path' => $relativePath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'file_size' => (int) $file->getSize(),
            'checksum_sha256' => $hash,
            'scan_status' => $scan->status,
            'scanned_at' => now(),
            'scanner' => $scan->scannerName,
            'scan_result' => $scan->message,
            'ocr_status' => 'pending',
            'ocr_error' => null,
            'extraction_method' => null,
            'extracted_text_compressed' => null,
            'text_extracted_at' => null,
            'processing_status' => ProcessingStatus::Pending->value,
            'change_summary' => $changeSummary,
            'uploaded_by' => $user->getKey(),
        ]);

        $document->update(['version_count' => $versionNumber]);

        DB::afterCommit(function () use ($version, $ingestAfterResponse): void {
            $pending = ProcessDocumentVersionJob::dispatch($version->getKey());

            if ($ingestAfterResponse) {
                $pending->afterResponse();
            }
        });

        return $version;
    }

    private function assertAllowedMime(UploadedFile $file): void
    {
        $this->assertSafeFilename($file);

        $mime = $file->getMimeType();
        $allowed = config('sentria.documents.allowed_mime_types', []);

        if (! is_string($mime) || ! in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException('File type is not permitted.');
        }
    }

    private function assertSafeFilename(UploadedFile $file): void
    {
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        if ($basename !== '' && str_contains($basename, '.')) {
            throw new InvalidArgumentException('Filenames with multiple extensions are not permitted.');
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, ''));

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Document::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
