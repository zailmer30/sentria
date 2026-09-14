<?php

namespace App\Http\Controllers;

use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AI\StructuredDocumentComparisonService;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiCompareController extends Controller
{
    public function __construct(
        private readonly StructuredDocumentComparisonService $comparison,
        private readonly DocumentAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.compare'), 403);

        $query = Document::query()
            ->with(['currentVersion', 'versions.uploader', 'author', 'committee'])
            ->orderByDesc('submitted_at');

        $this->access->scopeVisibleTo($query, $user);

        $documents = $query->limit(100)->get();

        return Inertia::render('Ai/Compare', [
            'documents' => $documents->map(fn (Document $document): array => [
                ...DocumentResource::summary($document),
                'versions' => $document->versions
                    ->sortByDesc('version_number')
                    ->values()
                    ->map(fn (DocumentVersion $version): array => DocumentResource::version($version))
                    ->all(),
            ])->values()->all(),
            'selected' => [
                'left_slug' => $request->string('a')->toString() ?: null,
                'right_slug' => $request->string('b')->toString() ?: null,
                'left_version_id' => $request->string('a_version')->toString() ?: null,
                'right_version_id' => $request->string('b_version')->toString() ?: null,
            ],
        ]);
    }

    public function compare(Request $request): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('ai.compare'), 403);

        $leftSlug = $request->string('a')->toString();
        $rightSlug = $request->string('b')->toString();

        abort_if($leftSlug === '' || $rightSlug === '', 422, 'Both documents are required.');

        /** @var Document $leftDocument */
        $leftDocument = Document::query()->where('slug', $leftSlug)->firstOrFail();
        /** @var Document $rightDocument */
        $rightDocument = Document::query()->where('slug', $rightSlug)->firstOrFail();

        $leftVersion = $this->resolveVersion($leftDocument, $request->string('a_version')->toString() ?: null);
        $rightVersion = $this->resolveVersion($rightDocument, $request->string('b_version')->toString() ?: null);

        $leftVersion->loadMissing('uploader');
        $rightVersion->loadMissing('uploader');

        $leftDocument->loadMissing(['author', 'committee']);
        $rightDocument->loadMissing(['author', 'committee']);

        $result = $this->comparison->compareVersions($user, $leftVersion, $rightVersion);

        $this->audit->record(
            event: 'ai.compare',
            category: 'ai',
            auditable: $leftDocument,
            actor: $user,
            context: [
                'left_document_id' => $leftDocument->getKey(),
                'right_document_id' => $rightDocument->getKey(),
                'left_version_id' => $leftVersion->getKey(),
                'right_version_id' => $rightVersion->getKey(),
                'cross_document' => $leftDocument->getKey() !== $rightDocument->getKey(),
            ],
            message: 'Document comparison generated.',
            isAiActor: true,
        );

        return Inertia::render('Ai/Compare', [
            'documents' => $this->documentOptions($user),
            'selected' => [
                'left_slug' => $leftDocument->slug,
                'right_slug' => $rightDocument->slug,
                'left_version_id' => (string) $leftVersion->getKey(),
                'right_version_id' => (string) $rightVersion->getKey(),
            ],
            'comparison' => [
                'left' => [
                    'document' => DocumentResource::summary($leftDocument),
                    'version' => DocumentResource::version($leftVersion),
                ],
                'right' => [
                    'document' => DocumentResource::summary($rightDocument),
                    'version' => DocumentResource::version($rightVersion),
                ],
                'result' => $result->toArray(),
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentOptions(User $user): array
    {
        $query = Document::query()
            ->with(['currentVersion', 'versions.uploader', 'author', 'committee'])
            ->orderByDesc('submitted_at');

        $this->access->scopeVisibleTo($query, $user);

        /** @var list<array<string, mixed>> $options */
        $options = array_values($query->limit(100)->get()->map(fn (Document $document): array => [
            ...DocumentResource::summary($document),
            'versions' => $document->versions
                ->sortByDesc('version_number')
                ->values()
                ->map(fn (DocumentVersion $version): array => DocumentResource::version($version))
                ->all(),
        ])->all());

        return $options;
    }

    private function resolveVersion(Document $document, ?string $versionId): DocumentVersion
    {
        if ($versionId !== null && $versionId !== '') {
            return DocumentVersion::query()
                ->where('document_id', $document->getKey())
                ->whereKey($versionId)
                ->firstOrFail();
        }

        $document->loadMissing('currentVersion');
        $current = $document->currentVersion;

        abort_unless($current !== null, 422, 'Document has no current version.');

        return $current;
    }
}
