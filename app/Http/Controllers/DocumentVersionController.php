<?php

namespace App\Http\Controllers;

use App\Enums\ProcessingStatus;
use App\Http\Requests\Documents\StoreDocumentVersionRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\SessionResource;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\DocumentVersion;
use App\Models\PrivateNote;
use App\Services\AI\StructuredDocumentComparisonService;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVersionController extends Controller
{
    public function __construct(
        private readonly DocumentVersionService $versions,
        private readonly StructuredDocumentComparisonService $comparison,
        private readonly AuditLogger $audit,
    ) {}

    public function store(StoreDocumentVersionRequest $request, Document $document): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $this->versions->uploadNewVersion(
            $document,
            $this->requireUser($request),
            $file,
            $request->validated('change_summary'),
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.version_uploaded');
    }

    public function retryProcessing(Request $request, Document $document, DocumentVersion $version): RedirectResponse
    {
        $this->authorize('uploadVersion', $document);

        abort_unless($version->document_id === $document->getKey(), 404);
        abort_unless($version->processing_status === ProcessingStatus::Failed, 422);

        $version->update([
            'ocr_status' => 'pending',
            'ocr_error' => null,
            'extraction_method' => null,
            'processing_status' => ProcessingStatus::Pending->value,
            'processing_error' => null,
            'processed_at' => null,
        ]);

        ProcessDocumentVersionJob::dispatch($version->getKey());

        $this->audit->record(
            event: 'document.processing.retry',
            category: 'documents',
            auditable: $document,
            actor: $this->requireUser($request),
            context: ['version' => $version->version_number],
            message: 'Document version processing queued for retry.',
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.processing_retry_queued');
    }

    public function download(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        $this->authorize('download', $document);

        abort_unless($version->document_id === $document->getKey(), 404);
        abort_unless($version->isSafeToServe(), 403);

        $user = $this->requireUser($request);

        $this->audit->record(
            event: 'document.download',
            category: 'documents',
            auditable: $document,
            actor: $user,
            context: ['version' => $version->version_number],
            message: 'Document version downloaded.',
        );

        return Storage::disk($version->disk)->download(
            $version->file_path,
            $version->original_filename,
            ['Content-Type' => $version->mime_type],
        );
    }

    public function preview(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        $this->authorize('download', $document);

        abort_unless($version->document_id === $document->getKey(), 404);
        abort_unless($version->isSafeToServe(), 403);
        abort_unless($version->mime_type === 'application/pdf', 422);

        $user = $this->requireUser($request);

        $this->audit->record(
            event: 'document.preview',
            category: 'documents',
            auditable: $document,
            actor: $user,
            context: ['version' => $version->version_number],
            message: 'Document version previewed.',
        );

        return Storage::disk($version->disk)->response(
            $version->file_path,
            $version->original_filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function viewer(Request $request, Document $document, DocumentVersion $version): Response
    {
        $this->authorize('download', $document);

        abort_unless($version->document_id === $document->getKey(), 404);
        abort_unless($version->isSafeToServe(), 403);

        $version->loadMissing('uploader');

        $user = $this->requireUser($request);

        $notes = $document->privateNotes()
            ->ownedBy($user)
            ->latest()
            ->get();

        $annotations = DocumentAnnotation::query()
            ->ownedBy($user)
            ->where('document_version_id', $version->getKey())
            ->first();

        return Inertia::render('Documents/View', [
            'document' => [
                ...DocumentResource::summary($document),
                'id' => $document->getKey(),
            ],
            'version' => DocumentResource::version($version),
            'privateNotes' => $notes
                ->map(fn (PrivateNote $note): array => SessionResource::privateNote($note))
                ->values()
                ->all(),
            'annotations' => $annotations === null ? [] : $annotations->payload,
        ]);
    }

    public function compare(Request $request, Document $document): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.compare'), 403);
        $this->authorize('view', $document);

        $fromId = $request->string('from')->toString();
        $toId = $request->string('to')->toString();

        /** @var DocumentVersion $from */
        $from = DocumentVersion::query()
            ->where('document_id', $document->getKey())
            ->whereKey($fromId)
            ->firstOrFail();

        /** @var DocumentVersion $to */
        $to = DocumentVersion::query()
            ->where('document_id', $document->getKey())
            ->whereKey($toId)
            ->firstOrFail();

        $from->loadMissing('uploader');
        $to->loadMissing('uploader');

        $result = $this->comparison->compareVersions($user, $from, $to);

        $this->audit->record(
            event: 'ai.compare',
            category: 'ai',
            auditable: $document,
            actor: $user,
            context: [
                'left_document_id' => $document->getKey(),
                'right_document_id' => $document->getKey(),
                'left_version_id' => $from->getKey(),
                'right_version_id' => $to->getKey(),
                'cross_document' => false,
            ],
            message: 'Document version comparison generated.',
            isAiActor: true,
        );

        return Inertia::render('Documents/Compare', [
            'document' => DocumentResource::summary($document),
            'from' => DocumentResource::version($from),
            'to' => DocumentResource::version($to),
            'comparison' => $result->toArray(),
        ]);
    }
}
