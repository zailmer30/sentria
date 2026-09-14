<?php

namespace App\Http\Controllers;

use App\Contracts\AI\DocumentSummarizationService;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSummaryController extends Controller
{
    public function __construct(private readonly DocumentSummarizationService $summarizer) {}

    public function store(Request $request, Document $document): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.summarize'), 403);
        $this->authorize('view', $document);

        $summary = $this->summarizer->summarize(
            user: $this->requireUser($request),
            document: $document,
            refresh: $request->boolean('refresh'),
        );

        return response()->json([
            'summary' => $summary->toMetadataJson(),
        ]);
    }
}
