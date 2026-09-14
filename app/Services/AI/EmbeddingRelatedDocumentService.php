<?php

namespace App\Services\AI;

use App\Contracts\AI\EmbeddingService;
use App\Contracts\AI\RelatedDocumentService;
use App\DTO\AI\RelatedDocumentsResult;
use App\DTO\AI\RelatedDocumentSuggestion;
use App\Models\Document;
use App\Models\DocumentEmbedding;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentTextStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Pgvector\Laravel\Distance;

class EmbeddingRelatedDocumentService implements RelatedDocumentService
{
    private const EXCERPT_LIMIT = 2000;

    private const HIGH_SIMILARITY = 0.70;

    private const MEDIUM_SIMILARITY = 0.45;

    private const LOW_SIMILARITY = 0.25;

    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly DocumentAccessService $access,
        private readonly AuditLogger $audit,
        private readonly DocumentTextStore $textStore,
    ) {}

    public function findRelated(User $user, Document $document, int $limit = 5): RelatedDocumentsResult
    {
        if (! $user->can('ai.use')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->access->userCanView($user, $document)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $document->loadMissing('currentVersion');
        $version = $document->currentVersion;
        $extracted = $version !== null ? $this->textStore->get($version) : null;

        $queryText = $this->buildQueryText($document, $extracted);

        if ($queryText === '') {
            $this->auditRelated($user, $document, []);

            return new RelatedDocumentsResult(suggestions: []);
        }

        $vector = $this->embeddings->embed([$queryText])[0] ?? [];

        if ($vector === []) {
            $this->auditRelated($user, $document, []);

            return new RelatedDocumentsResult(suggestions: []);
        }

        $visibleDocumentIds = Document::query()
            ->select('id')
            ->whereKeyNot($document->getKey())
            ->tap(fn ($builder) => $this->access->scopeVisibleTo($builder, $user));

        $rows = DocumentEmbedding::query()
            ->select('document_embeddings.*')
            ->whereIn('document_id', $visibleDocumentIds)
            ->nearestNeighbors('embedding', $vector, Distance::Cosine)
            ->limit(max($limit * 6, 24))
            ->get();

        $bestByDocument = [];

        foreach ($rows as $row) {
            /** @var DocumentEmbedding $row */
            $documentId = (string) $row->document_id;
            $distance = (float) ($row->neighbor_distance ?? 0.0);
            $similarity = max(0.0, 1.0 - $distance);

            if ($similarity < self::LOW_SIMILARITY) {
                continue;
            }

            if (! isset($bestByDocument[$documentId]) || $similarity > $bestByDocument[$documentId]['similarity']) {
                $bestByDocument[$documentId] = [
                    'embedding' => $row,
                    'similarity' => $similarity,
                ];
            }
        }

        uasort(
            $bestByDocument,
            static fn (array $left, array $right): int => $right['similarity'] <=> $left['similarity'],
        );

        $documentIds = array_slice(array_keys($bestByDocument), 0, $limit);

        if ($documentIds === []) {
            $this->auditRelated($user, $document, []);

            return new RelatedDocumentsResult(suggestions: []);
        }

        $documents = Document::query()
            ->whereIn('id', $documentIds)
            ->get()
            ->keyBy('id');

        $suggestions = [];

        foreach ($documentIds as $relatedId) {
            $related = $documents->get($relatedId);

            if ($related === null) {
                continue;
            }

            $match = $bestByDocument[$relatedId];
            /** @var DocumentEmbedding $embedding */
            $embedding = $match['embedding'];
            $similarity = (float) $match['similarity'];

            $suggestions[] = new RelatedDocumentSuggestion(
                documentId: (string) $related->getKey(),
                slug: (string) $related->slug,
                title: (string) $related->title,
                referenceNumber: $related->reference_number,
                documentTypeLabel: $related->document_type->label(),
                similarity: $similarity,
                relevanceBand: $this->relevanceBand($similarity),
                excerpt: Str::limit((string) $embedding->chunk_text, 240, '…'),
            );
        }

        $this->auditRelated($user, $document, array_map(
            static fn (RelatedDocumentSuggestion $suggestion): string => $suggestion->documentId,
            $suggestions,
        ));

        return new RelatedDocumentsResult(suggestions: $suggestions);
    }

    private function buildQueryText(Document $document, ?string $extractedText): string
    {
        $parts = array_filter([
            trim((string) $document->title),
            trim((string) ($document->abstract ?? '')),
            trim(Str::limit((string) ($extractedText ?? ''), self::EXCERPT_LIMIT, '…')),
        ]);

        return trim(implode("\n\n", $parts));
    }

    private function relevanceBand(float $similarity): string
    {
        if ($similarity >= self::HIGH_SIMILARITY) {
            return 'high';
        }

        if ($similarity >= self::MEDIUM_SIMILARITY) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  list<string>  $relatedDocumentIds
     */
    private function auditRelated(User $user, Document $document, array $relatedDocumentIds): void
    {
        $this->audit->record(
            event: 'ai.related_documents',
            category: 'ai',
            auditable: $document,
            actor: $user,
            context: [
                'source_document_id' => $document->getKey(),
                'related_document_ids' => $relatedDocumentIds,
                'result_count' => count($relatedDocumentIds),
            ],
            message: 'Related document suggestions generated.',
            isAiActor: true,
        );
    }
}
