<?php

namespace App\Services\Legislation;

use App\Contracts\Legislation\HoldsSignedCopy;
use App\Contracts\Malware\MalwareScanner;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegislativeSignedCopyService
{
    public function __construct(
        private readonly MalwareScanner $scanner,
        private readonly AuditLogger $audit,
    ) {}

    public function store(Ordinance|Resolution $record, User $actor, UploadedFile $file): void
    {
        $this->assertPdf($file);

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
            Storage::disk($previousDisk)->delete($previousPath);
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

        Storage::disk($disk)->delete($path);

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
