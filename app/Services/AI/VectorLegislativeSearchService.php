<?php

namespace App\Services\AI;

use App\Contracts\AI\EmbeddingService;
use App\Contracts\AI\LegislativeSearchService;
use App\DTO\AI\SearchHit;
use App\DTO\AI\SearchResults;
use App\Models\Document;
use App\Models\DocumentEmbedding;
use App\Models\User;
use App\Services\Documents\DocumentAccessService;
use Pgvector\Laravel\Distance;

class VectorLegislativeSearchService implements LegislativeSearchService
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly DocumentAccessService $access,
    ) {}

    public function search(User $user, string $query, int $limit = 8): SearchResults
    {
        $query = trim($query);

        if ($query === '') {
            return new SearchResults(query: $query, hits: []);
        }

        if (! $user->can('documents.view')) {
            return new SearchResults(query: $query, hits: []);
        }

        $vector = $this->embeddings->embed([$query])[0] ?? [];

        if ($vector === []) {
            return new SearchResults(query: $query, hits: []);
        }

        $visibleDocumentIds = Document::query()
            ->select('id')
            ->tap(fn ($builder) => $this->access->scopeVisibleTo($builder, $user));

        $rows = DocumentEmbedding::query()
            ->select('document_embeddings.*')
            ->whereIn('document_id', $visibleDocumentIds)
            ->nearestNeighbors('embedding', $vector, Distance::Cosine)
            ->limit($limit)
            ->get();

        $hits = $rows->map(function (DocumentEmbedding $row): SearchHit {
            $distance = (float) ($row->neighbor_distance ?? 0.0);

            return new SearchHit(
                embeddingId: (string) $row->getKey(),
                documentId: (string) $row->document_id,
                documentVersionId: (string) $row->document_version_id,
                chunkText: (string) $row->chunk_text,
                similarity: max(0.0, 1.0 - $distance),
                pageNumber: $row->page_number,
                sectionHeading: $row->section_heading,
                sectionNumber: $row->section_number,
            );
        })->all();

        return new SearchResults(query: $query, hits: array_values($hits));
    }
}
