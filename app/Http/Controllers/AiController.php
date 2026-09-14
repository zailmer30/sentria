<?php

namespace App\Http\Controllers;

use App\Contracts\AI\RAGService;
use App\DTO\AI\RagCitation;
use App\Http\Requests\Ai\AskLegislativeAiRequest;
use App\Models\AiConversation;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AiController extends Controller
{
    public function __construct(private readonly RAGService $rag) {}

    public function index(Request $request): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.use'), 403);

        $conversations = AiConversation::query()
            ->where('user_id', $user->getKey())
            ->where('is_archived', false)
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        $conversationPayload = [];

        foreach ($conversations as $conversation) {
            $lastMessageAt = $conversation->last_message_at;

            $conversationPayload[] = [
                'id' => (string) $conversation->getKey(),
                'title' => $conversation->title ?? ($user->locale === 'fil'
                    ? 'Walang pamagat na pag-uusap'
                    : 'Untitled conversation'),
                'last_message_at' => $lastMessageAt instanceof Carbon
                    ? $lastMessageAt->toIso8601String()
                    : null,
                'message_count' => (int) $conversation->message_count,
            ];
        }

        return Inertia::render('Ai/Index', [
            'conversations' => $conversationPayload,
            'can' => [
                'search' => $user->can('ai.search'),
                'compare' => $user->can('ai.compare'),
                'consistency' => $user->can('ai.checkConsistency'),
            ],
        ]);
    }

    public function ask(AskLegislativeAiRequest $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $validated = $request->validated();

        $documentContext = null;

        if (! empty($validated['document_slug'])) {
            $documentContext = Document::query()->where('slug', $validated['document_slug'])->first();
            abort_unless($documentContext !== null, 404);
            $this->authorize('view', $documentContext);
        }

        $result = $this->rag->ask(
            user: $user,
            question: $validated['question'],
            conversationId: $validated['conversation_id'] ?? null,
            documentContext: $documentContext,
        );

        return response()->json([
            'conversation_id' => $result->conversationId,
            'message' => [
                'id' => $result->messageId,
                'role' => 'assistant',
                'content' => $result->content,
                'model' => $result->model,
                'insufficient_evidence' => $result->insufficientEvidence,
                'citations' => array_map(
                    static fn (RagCitation $citation): array => [
                        'id' => $citation->id,
                        'document_id' => $citation->documentId,
                        'document_slug' => $citation->documentSlug,
                        'document_title' => $citation->documentTitle,
                        'page_number' => $citation->pageNumber,
                        'section_heading' => $citation->sectionHeading,
                        'section_number' => $citation->sectionNumber,
                        'quote' => $citation->quote,
                        'similarity' => $citation->similarity,
                        'url' => route('documents.show', $citation->documentSlug).self::citationQuery($citation),
                    ],
                    $result->citations,
                ),
            ],
        ]);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.use'), 403);
        abort_unless($conversation->user_id === $user->getKey(), 403);

        $conversation->load(['messages.citations.document']);

        return response()->json([
            'conversation' => [
                'id' => $conversation->getKey(),
                'title' => $conversation->title,
                'messages' => $conversation->messages->map(function ($message): array {
                    return [
                        'id' => $message->getKey(),
                        'role' => $message->role,
                        'content' => $message->content,
                        'created_at' => $message->created_at?->toIso8601String(),
                        'citations' => $message->citations->map(function ($citation): array {
                            $slug = $citation->document?->slug;

                            return [
                                'id' => $citation->getKey(),
                                'document_id' => $citation->document_id,
                                'document_slug' => $slug,
                                'document_title' => $citation->document?->title,
                                'page_number' => $citation->page_number,
                                'quote' => $citation->quote,
                                'url' => $slug !== null
                                    ? route('documents.show', $slug).($citation->page_number ? "?page={$citation->page_number}" : '')
                                    : null,
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ],
        ]);
    }

    private static function citationQuery(RagCitation $citation): string
    {
        $params = [];

        if ($citation->pageNumber !== null) {
            $params['page'] = $citation->pageNumber;
        }

        if ($citation->sectionNumber !== null) {
            $params['section'] = $citation->sectionNumber;
        }

        return $params === [] ? '' : '?'.http_build_query($params);
    }
}
