<?php

namespace App\Http\Controllers;

use App\Contracts\AI\ConsistencyCheckService;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentConsistencyController extends Controller
{
    public function __construct(private readonly ConsistencyCheckService $consistency) {}

    public function show(Request $request, Document $document): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.checkConsistency'), 403);
        $this->authorize('view', $document);

        return Inertia::render('Ai/Review', [
            'document' => [
                'slug' => $document->slug,
                'title' => $document->title,
                'reference_number' => $document->reference_number,
            ],
            'can' => [
                'run' => true,
            ],
        ]);
    }

    public function store(Request $request, Document $document): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.checkConsistency'), 403);
        $this->authorize('view', $document);

        $result = $this->consistency->check($user, $document);

        return response()->json($result->toArray());
    }
}
