<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentSearchIndex;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentSearchIndexer
{
    private const EXCERPT_LIMIT = 240;

    public function upsert(DocumentVersion $version, string $bodyText): void
    {
        $version->loadMissing('document');
        $document = $version->document;

        if (! $document instanceof Document) {
            return;
        }

        $title = (string) $document->title;
        $reference = (string) ($document->reference_number ?? '');
        $body = trim($bodyText);
        $excerpt = $body === '' ? null : Str::limit($body, self::EXCERPT_LIMIT, '…');

        $index = DocumentSearchIndex::query()->updateOrCreate(
            ['document_id' => $document->getKey()],
            [
                'document_version_id' => $version->getKey(),
                'excerpt' => $excerpt,
            ],
        );

        DB::update(
            <<<'SQL'
            update document_search_indexes
            set search_vector =
                setweight(to_tsvector('simple', coalesce(?, '')), 'A')
                || setweight(to_tsvector('simple', coalesce(?, '')), 'A')
                || setweight(to_tsvector('simple', coalesce(?, '')), 'D'),
                updated_at = ?
            where id = ?
            SQL,
            [$title, $reference, $body, now(), $index->getKey()],
        );
    }

    public function deleteForDocument(string $documentId): void
    {
        DocumentSearchIndex::query()->where('document_id', $documentId)->delete();
    }
}
