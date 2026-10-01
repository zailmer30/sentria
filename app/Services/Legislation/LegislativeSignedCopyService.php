<?php

namespace App\Services\Legislation;

use App\Contracts\Legislation\HoldsSignedCopy;
use App\Contracts\Malware\MalwareScanner;
use App\Models\Document;
use App\Models\DocumentEmbedding;
use App\Models\DocumentVersion;
use App\Models\Ordinance;
use App\Models\Publication;
use App\Models\Resolution;
use App\Models\User;
use App\Notifications\PublicationReturnedForSignedCopy;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentVersionService;
use App\Services\Notifications\InAppNotifier;
use App\States\Publication\Published;
use App\States\Publication\SecretariatReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegislativeSignedCopyService
{
    public function __construct(
        private readonly MalwareScanner $scanner,
        private readonly AuditLogger $audit,
        private readonly DocumentVersionService $versions,
        private readonly InAppNotifier $notifier,
    ) {}

    public function store(Ordinance|Resolution $record, User $actor, UploadedFile $file, bool $confirmUnpublish = false): void
    {
        $this->assertPdf($file);

        $published = $this->publishedPublications($record);

        if ($published->isNotEmpty()) {
            $this->storePublished($record, $actor, $file, $confirmUnpublish, $published);

            return;
        }

        $previousPath = $record->signed_copy_path;
        $previousDisk = $record->signed_copy_disk ?: 'local';
        $replacing = is_string($previousPath) && $previousPath !== '';

        $hash = hash_file('sha256', $file->getRealPath() ?: '') ?: hash('sha256', (string) microtime(true));
        $folder = $record instanceof Ordinance ? 'ordinances' : 'resolutions';
        $relativePath = sprintf(
            'legislation/%s/%s/signed-%s.pdf',
            $folder,
            $record->getKey(),
            substr($hash, 0, 16),
        );

        Storage::disk('local')->putFileAs(
            dirname($relativePath),
            $file,
            basename($relativePath),
        );

        $absolutePath = Storage::disk('local')->path($relativePath);
        $scan = $this->scanner->scan($absolutePath);

        if (! $scan->isSafeToServe()) {
            Storage::disk('local')->delete($relativePath);

            throw ValidationException::withMessages([
                'file' => 'The signed copy did not pass the security scan and was not attached.',
            ]);
        }

        $record->forceFill([
            'signed_copy_disk' => 'local',
            'signed_copy_path' => $relativePath,
            'signed_copy_filename' => $file->getClientOriginalName(),
            'signed_copy_mime' => 'application/pdf',
            'signed_copy_size' => (int) $file->getSize(),
            'signed_copy_checksum' => $hash,
            'signed_copy_scan_status' => $scan->status,
            'signed_copy_uploaded_at' => now(),
            'signed_copy_uploaded_by' => $actor->getKey(),
        ])->save();

        if ($replacing && $previousPath !== $relativePath) {
            $this->deleteStandaloneSignedCopy($previousDisk, $previousPath);
        }

        $this->audit->record(
            event: $replacing ? 'legislation.signed_copy.replace' : 'legislation.signed_copy.upload',
            category: 'legislation',
            auditable: $record,
            actor: $actor,
            new: [
                'filename' => $file->getClientOriginalName(),
                'checksum' => $hash,
            ],
            message: $replacing
                ? 'Signed legislative copy replaced.'
                : 'Signed legislative copy attached.',
        );
    }

    public function storeFromPath(
        Ordinance|Resolution $record,
        User $actor,
        string $absolutePath,
        string $filename,
    ): void {
        $file = new UploadedFile($absolutePath, $filename, 'application/pdf', UPLOAD_ERR_OK, true);

        $this->store($record, $actor, $file);
    }

    public function destroy(Ordinance|Resolution $record, User $actor): void
    {
        $path = $record->signed_copy_path;
        $disk = $record->signed_copy_disk ?: 'local';
        $filename = $record->signed_copy_filename;

        if (! is_string($path) || $path === '') {
            return;
        }

        $this->deleteStandaloneSignedCopy($disk, $path);

        $record->forceFill([
            'signed_copy_disk' => null,
            'signed_copy_path' => null,
            'signed_copy_filename' => null,
            'signed_copy_mime' => null,
            'signed_copy_size' => null,
            'signed_copy_checksum' => null,
            'signed_copy_scan_status' => null,
            'signed_copy_uploaded_at' => null,
            'signed_copy_uploaded_by' => null,
        ])->save();

        $this->audit->record(
            event: 'legislation.signed_copy.remove',
            category: 'legislation',
            auditable: $record,
            actor: $actor,
            old: ['filename' => $filename],
            message: 'Signed legislative copy removed.',
        );
    }

    public function stream(HoldsSignedCopy $record, bool $download, ?User $actor = null): StreamedResponse
    {
        abort_unless($record->hasSignedCopy(), 404);

        $path = $record->signed_copy_path;
        $disk = $record->signed_copy_disk ?: 'local';

        abort_unless(is_string($path) && $path !== '' && Storage::disk($disk)->exists($path), 404);

        if ($actor instanceof User) {
            $this->audit->record(
                event: $download ? 'legislation.signed_copy.download' : 'legislation.signed_copy.preview',
                category: 'legislation',
                auditable: $record instanceof Ordinance || $record instanceof Resolution ? $record : null,
                actor: $actor,
                context: ['filename' => $record->signed_copy_filename],
                message: $download
                    ? 'Signed legislative copy downloaded.'
                    : 'Signed legislative copy previewed.',
            );
        }

        $filename = $record->signed_copy_filename ?: 'signed-copy.pdf';

        return Storage::disk($disk)->response(
            $path,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $download ? 'attachment' : 'inline',
        );
    }

    /**
     * @param  Collection<int, Publication>  $published
     */
    private function storePublished(
        Ordinance|Resolution $record,
        User $actor,
        UploadedFile $file,
        bool $confirmUnpublish,
        Collection $published,
    ): void {
        $document = $record->document;

        if (! $document instanceof Document) {
            throw ValidationException::withMessages([
                'file' => 'A signed copy cannot be attached to a published record that has no document.',
            ]);
        }

        if (! $confirmUnpublish) {
            throw ValidationException::withMessages([
                'confirm_unpublish' => __('legislation.signed_copy_unpublish_required'),
            ]);
        }

        $previousPath = $record->signed_copy_path;
        $previousDisk = $record->signed_copy_disk ?: 'local';
        $replacing = is_string($previousPath) && $previousPath !== '';
        $changeSummary = $replacing ? 'Signed copy replaced' : 'Signed copy uploaded';

        $version = DB::transaction(function () use ($record, $actor, $file, $document, $published, $replacing, $changeSummary): DocumentVersion {
            $version = $this->versions->uploadNewVersion(
                $document,
                $actor,
                $file,
                $changeSummary,
                rejectUnsafeScan: true,
            );

            $record->forceFill([
                'signed_copy_disk' => $version->disk,
                'signed_copy_path' => $version->file_path,
                'signed_copy_filename' => $file->getClientOriginalName(),
                'signed_copy_mime' => 'application/pdf',
                'signed_copy_size' => (int) $version->file_size,
                'signed_copy_checksum' => $version->checksum_sha256,
                'signed_copy_scan_status' => $version->scan_status,
                'signed_copy_uploaded_at' => now(),
                'signed_copy_uploaded_by' => $actor->getKey(),
            ])->save();

            $document->forceFill([
                'is_public' => false,
                'published_at' => null,
            ])->save();

            DocumentEmbedding::query()
                ->where('document_id', $document->getKey())
                ->update(['is_public' => false]);

            foreach ($published as $publication) {
                $fromStatus = $publication->status->getValue();

                Publication::query()->whereKey($publication->getKey())->update([
                    'status' => SecretariatReview::$name,
                    'document_version_id' => $version->getKey(),
                    'published_at' => null,
                    'published_by' => null,
                    'unpublished_at' => now(),
                ]);

                $this->audit->record(
                    event: 'publication.signed_copy_rewind',
                    category: 'publications',
                    auditable: $publication,
                    actor: $actor,
                    old: ['status' => $fromStatus],
                    new: ['status' => SecretariatReview::$name],
                    message: 'Publication returned to secretariat review after a signed copy was uploaded.',
                );
            }

            $this->audit->record(
                event: $replacing ? 'legislation.signed_copy.replace' : 'legislation.signed_copy.upload',
                category: 'legislation',
                auditable: $record,
                actor: $actor,
                new: [
                    'filename' => $file->getClientOriginalName(),
                    'checksum' => $version->checksum_sha256,
                    'version' => $version->version_number,
                ],
                message: $replacing
                    ? 'Signed legislative copy replaced.'
                    : 'Signed legislative copy attached.',
            );

            return $version;
        });

        if ($replacing && is_string($previousPath) && $previousPath !== '' && $previousPath !== $version->file_path) {
            $this->deleteStandaloneSignedCopy($previousDisk, $previousPath);
        }

        $publication = $published->first();

        if ($publication instanceof Publication) {
            $this->notifier->send(
                User::permission('publications.review')->where('is_active', true)->get(),
                new PublicationReturnedForSignedCopy($document, $publication),
            );
        }
    }

    /**
     * @return Collection<int, Publication>
     */
    private function publishedPublications(Ordinance|Resolution $record): Collection
    {
        $document = $record->document;

        if (! $document instanceof Document) {
            return new Collection;
        }

        return $document->publications()
            ->where('status', Published::$name)
            ->get();
    }

    private function deleteStandaloneSignedCopy(string $disk, string $path): void
    {
        $usedByVersion = DocumentVersion::query()->where('file_path', $path)->exists();

        if ($usedByVersion) {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    private function assertPdf(UploadedFile $file): void
    {
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        if ($basename !== '' && str_contains($basename, '.')) {
            throw ValidationException::withMessages([
                'file' => 'Filenames with multiple extensions are not permitted.',
            ]);
        }

        $mime = $file->getMimeType();
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($mime !== 'application/pdf' || $extension !== 'pdf') {
            throw ValidationException::withMessages([
                'file' => 'The signed copy must be a PDF.',
            ]);
        }
    }
}
