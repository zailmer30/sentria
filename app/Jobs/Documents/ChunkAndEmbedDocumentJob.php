<?php

namespace App\Jobs\Documents;

use App\Contracts\AI\EmbeddingService;
use App\Models\DocumentEmbedding;
use App\Models\DocumentVersion;
use App\Services\Documents\DocumentChunker;
use App\Services\Documents\DocumentTextStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Pgvector\Laravel\Vector;
use RuntimeException;

class ChunkAndEmbedDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $documentVersionId,
    ) {}

    public function handle(DocumentChunker $chunker, EmbeddingService $embeddings, DocumentTextStore $textStore): void
    {
        $version = DocumentVersion::query()->with('document')->find($this->documentVersionId);

        if ($version === null) {
            return;
        }

        $text = trim((string) $textStore->get($version));

        if ($text === '') {
            throw new RuntimeException('Cannot chunk document version without extracted text.');
        }

        $chunks = $chunker->chunk($text);

        if ($chunks === []) {
            throw new RuntimeException('Chunker produced no chunks.');
        }

        DocumentEmbedding::query()
            ->where('document_version_id', $version->getKey())
            ->delete();

        $document = $version->document;

        if ($document === null) {
            throw new RuntimeException('Document missing for version.');
        }

        $vectors = $embeddings->embed(array_map(static fn ($chunk) => $chunk->text, $chunks));
        $expectedDimensions = $embeddings->dimensions();

        foreach ($chunks as $offset => $chunk) {
            $vector = $vectors[$offset] ?? null;

            if (! is_array($vector) || count($vector) !== $expectedDimensions) {
                throw new RuntimeException(
                    'Embedding dimension mismatch: expected '.$expectedDimensions.', got '.count($vector ?? []),
                );
            }

            DocumentEmbedding::query()->create([
                'document_id' => $version->document_id,
                'document_version_id' => $version->getKey(),
                'chunk_index' => $chunk->index,
                'chunk_text' => $chunk->text,
                'token_count' => (int) round(str_word_count($chunk->text) * 1.3),
                'page_number' => $chunk->pageNumber,
                'section_heading' => $chunk->sectionHeading !== null
                    ? Str::limit($chunk->sectionHeading, 255, '')
                    : null,
                'section_number' => $chunk->sectionNumber !== null
                    ? Str::limit($chunk->sectionNumber, 255, '')
                    : null,
                'char_start' => $chunk->charStart,
                'char_end' => $chunk->charEnd,
                'content_hash' => hash('sha256', $chunk->text),
                'model' => $embeddings->modelName(),
                'confidentiality' => $document->confidentiality->value,
                'is_public' => (bool) $document->is_public,
                'embedding' => new Vector($vector),
            ]);
        }
    }
}
