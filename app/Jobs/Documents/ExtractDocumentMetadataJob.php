<?php

namespace App\Jobs\Documents;

use App\Models\DocumentVersion;
use App\Services\Documents\DocumentMetadataExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractDocumentMetadataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $documentVersionId,
    ) {}

    public function handle(DocumentMetadataExtractor $extractor): void
    {
        $version = DocumentVersion::query()->find($this->documentVersionId);

        if ($version === null) {
            return;
        }

        $extractor->extract($version);
    }
}
