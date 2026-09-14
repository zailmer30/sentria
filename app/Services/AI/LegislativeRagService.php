<?php

namespace App\Services\AI;

use App\Contracts\AI\ChatCompletionService;
use App\Contracts\AI\LegislativeSearchService;
use App\Contracts\AI\RAGService;
use App\DTO\AI\ChatMessage;
use App\DTO\AI\ContextChunk;
use App\DTO\AI\RagCitation;
use App\DTO\AI\RagResult;
use App\DTO\AI\SearchHit;
use App\Models\AiCitation;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Document;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use App\Support\AI\InsufficientEvidence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LegislativeRagService implements RAGService
{
    public function __construct(
        private readonly LegislativeSearchService $search,
        private readonly ChatCompletionService $chat,
        private readonly PromptLoader $prompts,
        private readonly AuditLogger $audit,
        private readonly DocumentAccessService $access,
    ) {}

    public function ask(
        User $user,
        string $question,
        ?string $conversationId = null,
        ?Document $documentContext = null,
    ): RagResult {
        if (! $user->can('ai.use')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $question = trim($question);

        abort_if($question === '', 422, 'Question is required.');

        if ($documentContext !== null && ! $this->access->userCanView($user, $documentContext)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $conversation = $this->resolveConversation($user, $conversationId, $documentContext);
        $startedAt = microtime(true);

        AiMessage::query()->create([
            'ai_conversation_id' => $conversation->getKey(),
            'role' => 'user',
            'content' => $question,
        ]);

        $topK = (int) config('sentria.ai.retrieval_top_k', 8);
        $searchResults = $this->search->search($user, $question, $topK);
        $contextChunks = $this->buildContextChunks($searchResults->hits);

        $history = $this->conversationHistory($conversation, excludeLatest: true);
        $userPrompt = $this->buildUserPrompt($question, $contextChunks, $documentContext);
        $systemPrompt = $this->prompts->load('rag_system');

        $chatResult = $this->chat->complete(
            $systemPrompt,
            [...$history, new ChatMessage('user', $userPrompt)],
            ['context_chunks' => $contextChunks, 'mode' => 'rag'],
        );

        $insufficient = trim($chatResult->content) === InsufficientEvidence::PHRASE
            || $contextChunks === [];

        $assistantMessage = AiMessage::query()->create([
            'ai_conversation_id' => $conversation->getKey(),
            'role' => 'assistant',
            'content' => $chatResult->content,
            'model' => $chatResult->model,
            'input_tokens' => $chatResult->inputTokens,
            'output_tokens' => $chatResult->outputTokens,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $citations = $insufficient
            ? []
            : $this->persistCitations($assistantMessage, $contextChunks, $chatResult->content);

        $conversation->update([
            'title' => $conversation->title ?? Str::limit($question, 120, '…'),
            'model' => $chatResult->model,
            'system_prompt_version' => 'rag_system.v1',
            'message_count' => $conversation->messages()->count(),
            'last_message_at' => now(),
        ]);

        $retrievedDocumentIds = collect($contextChunks)
            ->pluck('documentId')
            ->unique()
            ->values()
            ->all();

        $this->audit->record(
            event: 'ai.rag.query',
            category: 'ai',
            auditable: $conversation,
            actor: $user,
            context: [
                'question' => $question,
                'conversation_id' => $conversation->getKey(),
                'message_id' => $assistantMessage->getKey(),
                'retrieved_document_ids' => $retrievedDocumentIds,
                'sources' => collect($contextChunks)->map(fn (ContextChunk $chunk): array => [
                    'embedding_id' => $chunk->embeddingId,
                    'document_id' => $chunk->documentId,
                    'document_slug' => $chunk->documentSlug,
                    'document_title' => $chunk->documentTitle,
                    'page_number' => $chunk->pageNumber,
                    'section_heading' => $chunk->sectionHeading,
                    'section_number' => $chunk->sectionNumber,
                    'similarity' => $chunk->similarity,
                ])->values()->all(),
                'model' => $chatResult->model,
                'response' => $chatResult->content,
                'usage' => [
                    'input_tokens' => $chatResult->inputTokens,
                    'output_tokens' => $chatResult->outputTokens,
                ],
                'insufficient_evidence' => $insufficient,
                'document_context_id' => $documentContext?->getKey(),
            ],
            message: 'Legislative AI RAG query completed.',
        );

        return new RagResult(
            conversationId: (string) $conversation->getKey(),
            messageId: (string) $assistantMessage->getKey(),
            content: $chatResult->content,
            citations: $citations,
            model: $chatResult->model,
            inputTokens: $chatResult->inputTokens,
            outputTokens: $chatResult->outputTokens,
            insufficientEvidence: $insufficient,
        );
    }

    private function resolveConversation(
        User $user,
        ?string $conversationId,
        ?Document $documentContext,
    ): AiConversation {
        if ($conversationId !== null) {
            /** @var AiConversation|null $existing */
            $existing = AiConversation::query()
                ->whereKey($conversationId)
                ->where('user_id', $user->getKey())
                ->first();

            abort_unless($existing instanceof AiConversation, 404);

            return $existing;
        }

        return AiConversation::query()->create([
            'user_id' => $user->getKey(),
            'title' => null,
            'context_type' => $documentContext !== null ? $documentContext->getMorphClass() : null,
            'context_id' => $documentContext?->getKey(),
        ]);
    }

    /**
     * @param  list<SearchHit>  $hits
     * @return list<ContextChunk>
     */
    private function buildContextChunks(array $hits): array
    {
        if ($hits === []) {
            return [];
        }

        $documentIds = collect($hits)->pluck('documentId')->unique()->all();

        /** @var Collection<string, Document> $documents */
        $documents = Document::query()
            ->whereIn('id', $documentIds)
            ->get(['id', 'title', 'slug'])
            ->keyBy('id');

        $chunks = [];

        foreach ($hits as $offset => $hit) {
            $document = $documents->get($hit->documentId);

            if ($document === null) {
                continue;
            }

            $chunks[] = new ContextChunk(
                index: $offset + 1,
                embeddingId: $hit->embeddingId,
                documentId: $hit->documentId,
                documentVersionId: $hit->documentVersionId,
                documentTitle: (string) $document->title,
                documentSlug: (string) $document->slug,
                chunkText: $hit->chunkText,
                similarity: $hit->similarity,
                pageNumber: $hit->pageNumber,
                sectionHeading: $hit->sectionHeading,
                sectionNumber: $hit->sectionNumber,
            );
        }

        return $chunks;
    }

    /**
     * @param  list<ContextChunk>  $chunks
     */
    private function buildUserPrompt(string $question, array $chunks, ?Document $documentContext): string
    {
        $lines = [];

        if ($documentContext !== null) {
            $lines[] = "Focus context document: {$documentContext->title} (slug: {$documentContext->slug}).";
            $lines[] = '';
        }

        $lines[] = "Question: {$question}";
        $lines[] = '';
        $lines[] = 'Authorized source chunks:';

        if ($chunks === []) {
            $lines[] = '(none retrieved)';
        } else {
            foreach ($chunks as $chunk) {
                $meta = collect([
                    "document=\"{$chunk->documentTitle}\"",
                    "slug={$chunk->documentSlug}",
                    $chunk->pageNumber !== null ? "page={$chunk->pageNumber}" : null,
                    $chunk->sectionNumber !== null ? "section={$chunk->sectionNumber}" : null,
                    $chunk->sectionHeading !== null ? "heading=\"{$chunk->sectionHeading}\"" : null,
                ])->filter()->implode('; ');

                $lines[] = "[{$chunk->index}] {$meta}";
                $lines[] = $chunk->chunkText;
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<ChatMessage>
     */
    private function conversationHistory(AiConversation $conversation, bool $excludeLatest): array
    {
        $query = $conversation->messages()->orderBy('created_at');

        if ($excludeLatest) {
            $query->where('id', '!=', $conversation->messages()->latest('created_at')->value('id'));
        }

        return array_values($query
            ->get(['role', 'content'])
            ->map(fn (AiMessage $message): ChatMessage => new ChatMessage(
                role: (string) $message->role,
                content: (string) $message->content,
            ))
            ->all());
    }

    /**
     * @param  list<ContextChunk>  $chunks
     * @return list<RagCitation>
     */
    private function persistCitations(AiMessage $message, array $chunks, string $answer): array
    {
        $referenced = $this->referencedChunkIndexes($answer, count($chunks));
        $selected = $referenced === []
            ? $chunks
            : array_values(array_filter($chunks, fn (ContextChunk $chunk): bool => in_array($chunk->index, $referenced, true)));

        $citations = [];

        foreach ($selected as $rank => $chunk) {
            $record = AiCitation::query()->create([
                'ai_message_id' => $message->getKey(),
                'document_id' => $chunk->documentId,
                'document_version_id' => $chunk->documentVersionId,
                'document_embedding_id' => $chunk->embeddingId,
                'rank' => $rank + 1,
                'similarity' => $chunk->similarity,
                'page_number' => $chunk->pageNumber,
                'quote' => Str::limit(trim($chunk->chunkText), 500, '…'),
            ]);

            $citations[] = new RagCitation(
                id: (string) $record->getKey(),
                documentId: $chunk->documentId,
                documentSlug: $chunk->documentSlug,
                documentTitle: $chunk->documentTitle,
                documentVersionId: $chunk->documentVersionId,
                embeddingId: $chunk->embeddingId,
                rank: $rank + 1,
                similarity: $chunk->similarity,
                pageNumber: $chunk->pageNumber,
                sectionHeading: $chunk->sectionHeading,
                sectionNumber: $chunk->sectionNumber,
                quote: $record->quote,
            );
        }

        return $citations;
    }

    /**
     * @return list<int>
     */
    private function referencedChunkIndexes(string $answer, int $chunkCount): array
    {
        preg_match_all('/\[(\d+)\]/', $answer, $matches);

        $indexes = [];

        foreach ($matches[1] as $value) {
            $index = (int) $value;

            if ($index >= 1 && $index <= $chunkCount) {
                $indexes[] = $index;
            }
        }

        return array_values(array_unique($indexes));
    }
}
