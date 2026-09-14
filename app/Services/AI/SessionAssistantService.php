<?php

namespace App\Services\AI;

use App\Contracts\AI\DocumentSummarizationService;
use App\Contracts\AI\LegislativeSearchService;
use App\Contracts\AI\RAGService;
use App\Contracts\AI\RelatedDocumentService;
use App\DTO\AI\RagResult;
use App\DTO\AI\SearchHit;
use App\DTO\AI\SessionAssistantContext;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\SessionResource;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentTextStore;
use App\Services\Sessions\AgendaService;
use App\States\Session\InSession;
use App\States\Session\Suspended;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SessionAssistantService
{
    private const SUMMARY_EXCERPT_LIMIT = 480;

    private const RELATED_LIMIT = 5;

    private const SIMILAR_LIMIT = 5;

    public function __construct(
        private readonly AgendaService $agenda,
        private readonly DocumentAccessService $access,
        private readonly DocumentSummarizationService $summaries,
        private readonly RelatedDocumentService $relatedDocuments,
        private readonly LegislativeSearchService $search,
        private readonly RAGService $rag,
        private readonly AuditLogger $audit,
        private readonly DocumentTextStore $textStore,
    ) {}

    public function context(User $user, LegislativeSession $session): SessionAssistantContext
    {
        $this->assertAvailable($user, $session);

        $session->loadMissing([
            'agendaItems.document.versions.uploader',
            'agendaItems.document.currentVersion',
        ]);

        $current = $this->resolveCurrentAgendaItem($session);

        if ($current === null) {
            return new SessionAssistantContext(
                available: true,
                currentAgendaItem: null,
            );
        }

        $document = $current->document;
        $canViewDocument = $document instanceof Document && $this->access->userCanView($user, $document);

        $summary = null;
        $relatedLegislation = [];
        $previousSimilar = [];
        $documentHistory = [];
        $documentPayload = null;
        $documentRestricted = false;

        if ($document instanceof Document) {
            $documentPayload = [
                'id' => $document->getKey(),
                'slug' => $document->slug,
                'title' => $document->title,
            ];

            if ($canViewDocument) {
                $document->loadMissing('versions.uploader');
                $summary = $this->resolveSummary($document);
                $relatedLegislation = $this->relatedDocuments
                    ->findRelated($user, $document, self::RELATED_LIMIT)
                    ->toArray()['suggestions'];
                $previousSimilar = $this->findPreviousSimilar($user, $document);
                $documentHistory = array_values($document->versions
                    ->sortByDesc('version_number')
                    ->map(fn ($version): array => DocumentResource::version($version))
                    ->all());
            } else {
                $documentRestricted = true;
            }
        }

        return new SessionAssistantContext(
            available: true,
            currentAgendaItem: SessionResource::agendaItem($current),
            document: $documentPayload,
            summary: $summary,
            documentRestricted: $documentRestricted,
            relatedLegislation: $relatedLegislation,
            previousSimilar: $previousSimilar,
            documentHistory: $documentHistory,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function search(User $user, LegislativeSession $session, string $query, int $limit = 8): array
    {
        $this->assertAvailable($user, $session);

        $query = trim($query);
        abort_if($query === '', 422, 'Query is required.');

        $results = $this->search->search($user, $query, $limit);
        $hits = $this->enrichSearchHits($results->hits);

        $this->audit->record(
            event: 'ai.session_assistant.search',
            category: 'ai',
            auditable: $session,
            actor: $user,
            context: [
                'session_id' => $session->getKey(),
                'query' => $query,
                'result_count' => count($hits),
                'document_ids' => collect($hits)->pluck('document_id')->unique()->values()->all(),
            ],
            message: 'Session assistant search completed.',
            isAiActor: true,
        );

        return [
            'query' => $results->query,
            'hits' => $hits,
        ];
    }

    public function ask(User $user, LegislativeSession $session, string $question): RagResult
    {
        $this->assertAvailable($user, $session);

        $question = trim($question);
        abort_if($question === '', 422, 'Question is required.');

        $session->loadMissing(['agendaItems.document']);
        $current = $this->resolveCurrentAgendaItem($session);
        $documentContext = null;

        if ($current?->document instanceof Document && $this->access->userCanView($user, $current->document)) {
            $documentContext = $current->document;
        }

        $result = $this->rag->ask(
            user: $user,
            question: $question,
            conversationId: null,
            documentContext: $documentContext,
        );

        $this->audit->record(
            event: 'ai.session_assistant.query',
            category: 'ai',
            auditable: $session,
            actor: $user,
            context: [
                'session_id' => $session->getKey(),
                'agenda_item_id' => $current?->getKey(),
                'document_id' => $documentContext?->getKey(),
                'question' => $question,
                'conversation_id' => $result->conversationId,
                'message_id' => $result->messageId,
                'insufficient_evidence' => $result->insufficientEvidence,
            ],
            message: 'Session assistant RAG query completed.',
            isAiActor: true,
        );

        return $result;
    }

    public function isActiveSession(LegislativeSession $session): bool
    {
        return $session->status instanceof InSession || $session->status instanceof Suspended;
    }

    private function assertAvailable(User $user, LegislativeSession $session): void
    {
        if (! $user->can('view', $session)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $user->can('ai.use')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->isActiveSession($session)) {
            throw new InvalidArgumentException('Session assistant is only available during active sessions.');
        }
    }

    private function resolveCurrentAgendaItem(LegislativeSession $session): ?AgendaItem
    {
        $current = $this->agenda->currentItem($session);

        if ($current instanceof AgendaItem) {
            return $current;
        }

        return $this->agenda->nextPendingItem($session);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSummary(Document $document): ?array
    {
        $stored = $this->summaries->loadStoredSummary($document);

        if ($stored !== null) {
            return $stored->toMetadataJson();
        }

        $document->loadMissing('currentVersion');
        $version = $document->currentVersion;
        $text = $version !== null ? trim((string) $this->textStore->get($version)) : '';

        if ($text !== '') {
            return $this->excerptSummary(Str::limit($text, self::SUMMARY_EXCERPT_LIMIT, '…'));
        }

        $abstract = trim((string) ($document->abstract ?? ''));

        if ($abstract !== '') {
            return $this->excerptSummary(Str::limit($abstract, self::SUMMARY_EXCERPT_LIMIT, '…'));
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function excerptSummary(string $text): array
    {
        return [
            'executive_summary' => $text,
            'purpose' => '',
            'key_provisions' => [],
            'important_dates' => [],
            'financial_info' => null,
            'affected_offices' => [],
            'related_docs_hints' => [],
            'potential_issues' => [],
            'model' => 'excerpt',
            'generated_at' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findPreviousSimilar(User $user, Document $document): array
    {
        $query = trim(implode(' ', array_filter([
            (string) $document->title,
            (string) ($document->abstract ?? ''),
        ])));

        if ($query === '') {
            return [];
        }

        $results = $this->search->search($user, $query, self::SIMILAR_LIMIT + 1);

        return $this->enrichSearchHits(
            array_values(array_filter(
                $results->hits,
                static fn (SearchHit $hit): bool => $hit->documentId !== (string) $document->getKey(),
            )),
        );
    }

    /**
     * @param  list<SearchHit>  $hits
     * @return list<array<string, mixed>>
     */
    private function enrichSearchHits(array $hits): array
    {
        if ($hits === []) {
            return [];
        }

        $documentIds = collect($hits)->pluck('documentId')->unique()->all();

        /** @var Collection<string, Document> $documents */
        $documents = Document::query()
            ->whereIn('id', $documentIds)
            ->get(['id', 'title', 'slug', 'reference_number'])
            ->keyBy('id');

        $enriched = [];

        foreach ($hits as $hit) {
            $document = $documents->get($hit->documentId);

            if ($document === null) {
                continue;
            }

            $enriched[] = [
                'embedding_id' => $hit->embeddingId,
                'document_id' => $hit->documentId,
                'document_slug' => $document->slug,
                'document_title' => $document->title,
                'reference_number' => $document->reference_number,
                'chunk_text' => Str::limit(trim($hit->chunkText), 240, '…'),
                'similarity' => round($hit->similarity, 4),
                'page_number' => $hit->pageNumber,
                'section_heading' => $hit->sectionHeading,
                'section_number' => $hit->sectionNumber,
                'url' => route('documents.show', $document->slug),
            ];
        }

        return $enriched;
    }
}
