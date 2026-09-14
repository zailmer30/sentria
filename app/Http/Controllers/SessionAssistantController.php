<?php

namespace App\Http\Controllers;

use App\DTO\AI\RagCitation;
use App\Models\LegislativeSession;
use App\Services\AI\SessionAssistantService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SessionAssistantController extends Controller
{
    public function __construct(private readonly SessionAssistantService $assistant) {}

    public function show(Request $request, LegislativeSession $session): JsonResponse
    {
        $user = $this->requireUser($request);

        try {
            $context = $this->assistant->context($user, $session);
        } catch (AuthorizationException) {
            abort(403);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json($context->toArray());
    }

    public function search(Request $request, LegislativeSession $session): JsonResponse
    {
        $user = $this->requireUser($request);

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        try {
            $results = $this->assistant->search(
                user: $user,
                session: $session,
                query: $validated['query'],
                limit: (int) ($validated['limit'] ?? 8),
            );
        } catch (AuthorizationException) {
            abort(403);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json($results);
    }

    public function ask(Request $request, LegislativeSession $session): JsonResponse
    {
        $user = $this->requireUser($request);

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            $result = $this->assistant->ask(
                user: $user,
                session: $session,
                question: $validated['question'],
            );
        } catch (AuthorizationException) {
            abort(403);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

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
                        'url' => route('documents.show', $citation->documentSlug),
                    ],
                    $result->citations,
                ),
            ],
        ]);
    }
}
