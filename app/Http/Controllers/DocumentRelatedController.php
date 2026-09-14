<?php

namespace App\Http\Controllers;

use App\Contracts\AI\RelatedDocumentService;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRelatedController extends Controller
{
    public function __construct(private readonly RelatedDocumentService $relatedDocuments) {}

    public function index(Request $request, Document $document): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.use'), 403);
        $this->authorize('view', $document);

        $result = $this->relatedDocuments->findRelated(
            user: $user,
            document: $document,
            limit: (int) $request->integer('limit', 5),
        );

        return response()->json($result->toArray());
    }
}
