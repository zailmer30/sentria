<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\UpdateDocumentAnnotationRequest;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentAnnotationController extends Controller
{
    public function show(Request $request, Document $document, DocumentVersion $version): JsonResponse
    {
        $this->authorize('download', $document);

        abort_unless($version->document_id === $document->getKey(), 404);
        abort_unless($version->isSafeToServe(), 403);

        $annotation = DocumentAnnotation::query()
            ->ownedBy($this->requireUser($request))
            ->where('document_version_id', $version->getKey())
            ->first();

        /** @var list<mixed> $payload */
        $payload = $annotation === null || ! is_array($annotation->payload)
            ? []
            : array_values($annotation->payload);

        return response()->json(['payload' => $payload]);
    }

    public function update(UpdateDocumentAnnotationRequest $request, Document $document, DocumentVersion $version): JsonResponse
    {
        abort_unless($version->document_id === $document->getKey(), 404);
        abort_unless($version->isSafeToServe(), 403);

        $validated = $request->validated();

        /** @var array<int, mixed> $payload */
        $payload = $validated['payload'];

        DocumentAnnotation::query()->updateOrCreate(
            [
                'user_id' => $this->requireUser($request)->getKey(),
                'document_version_id' => $version->getKey(),
            ],
            [
                'payload' => $payload,
            ],
        );

        return response()->json(['saved' => true]);
    }
}
