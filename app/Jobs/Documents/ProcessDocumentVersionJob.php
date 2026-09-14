<?php

namespace App\Jobs\Documents;

use App\Enums\ProcessingStatus;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Notifications\DocumentProcessingCompleted;
use App\Notifications\DocumentProcessingFailed;
use App\Services\Notifications\InAppNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

class ProcessDocumentVersionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $documentVersionId,
    ) {}

    public function uniqueId(): string
    {
        return $this->documentVersionId;
    }

    public function handle(InAppNotifier $notifier): void
    {
        $version = DocumentVersion::query()->with(['document.author', 'uploader'])->find($this->documentVersionId);

        if ($version === null) {
            return;
        }

        if (! $version->isSafeToServe()) {
            $version->update([
                'processing_status' => ProcessingStatus::Failed->value,
                'processing_error' => 'Malware scan did not pass.',
            ]);

            $this->notifyFailed($notifier, $version, 'Malware scan did not pass.');

            return;
        }

        $version->update([
            'processing_status' => ProcessingStatus::Processing->value,
            'processing_error' => null,
        ]);

        try {
            Bus::dispatchSync(new RunOcrJob($version->getKey()));
            Bus::dispatchSync(new ExtractDocumentMetadataJob($version->getKey()));
            Bus::dispatchSync(new ChunkAndEmbedDocumentJob($version->getKey()));

            $version->refresh()->update([
                'processing_status' => ProcessingStatus::Completed->value,
                'processing_error' => null,
                'processed_at' => now(),
            ]);

            $version->loadMissing(['document.author', 'uploader']);
            $document = $version->document;

            if ($document !== null) {
                $notifier->send(
                    $this->processingRecipients($version),
                    new DocumentProcessingCompleted($document, $version),
                );
            }
        } catch (Throwable $exception) {
            $version->refresh()->update([
                'processing_status' => ProcessingStatus::Failed->value,
                'processing_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        // The queue worker only calls failed() after tries are exhausted. Some
        // sync dispatch paths still invoke it on the first throw — skip those.
        if ($this->job !== null && $this->attempts() < $this->tries) {
            return;
        }

        $version = DocumentVersion::query()->with(['document.author', 'uploader'])->find($this->documentVersionId);

        if ($version === null || $version->document === null) {
            return;
        }

        // Only notify once permanent failure is recorded — not on intermediate retries.
        if ($version->processing_status !== ProcessingStatus::Failed) {
            return;
        }

        $this->notifyFailed(
            app(InAppNotifier::class),
            $version,
            $exception?->getMessage() ?? $version->processing_error,
        );
    }

    private function notifyFailed(InAppNotifier $notifier, DocumentVersion $version, ?string $error): void
    {
        $document = $version->document;

        if ($document === null) {
            return;
        }

        $notifier->send(
            $this->processingRecipients($version),
            new DocumentProcessingFailed($document, $version, $error),
        );
    }

    /**
     * @return list<User|null>
     */
    private function processingRecipients(DocumentVersion $version): array
    {
        return [
            $version->uploader,
            $version->document?->author,
        ];
    }
}
