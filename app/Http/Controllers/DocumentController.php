<?php

namespace App\Http\Controllers;

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\ProcessingStatus;
use App\Enums\UserRole;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PublicationResource;
use App\Models\Committee;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DocumentSubmitted;
use App\Services\AI\LegislativeDocumentSummarizer;
use App\Services\Audit\AuditLogger;
use App\Services\Committees\CommitteeReportNumberAllocator;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentReferenceAllocator;
use App\Services\Documents\DocumentVersionService;
use App\Services\Legislation\LegislativeHistoryService;
use App\Services\Notifications\InAppNotifier;
use App\Services\Sessions\VotingService;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\Approved;
use App\States\Document\Archive;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview;
use App\States\Document\ReturnedForRevision;
use App\States\Document\SecretariatReview;
use App\States\Document\Submitted;
use App\States\Document\Transmittal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentAccessService $access,
        private readonly DocumentVersionService $versions,
        private readonly AuditLogger $audit,
        private readonly GuardedStateTransition $transitions,
        private readonly LegislativeDocumentSummarizer $summaries,
        private readonly LegislativeHistoryService $history,
        private readonly VotingService $voting,
        private readonly InAppNotifier $notifier,
    ) {}

    public function index(Request $request, DocumentReferenceAllocator $references): Response
    {
        $this->authorize('viewAny', Document::class);

        $user = $this->requireUser($request);

        $query = Document::query()
            ->with(['author', 'committee', 'currentVersion'])
            ->latest('submitted_at');

        $this->access->scopeVisibleTo($query, $user);

        if ($request->filled('type')) {
            $query->where('document_type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('committee')) {
            $query->where('committee_id', $request->string('committee')->toString());
        }

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('processing')) {
            $processing = $request->string('processing')->toString();
            $query->whereHas('currentVersion', fn ($versionQuery) => $versionQuery->where('processing_status', $processing));
        }

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        // Counted before the card scope so the four figures stay about the
        // finding aid, not about the subset the reader just clicked into.
        $summary = $this->summarize($query);
        $scope = $this->indexScope($request);
        $this->applyIndexScope($query, $scope);

        $documents = $query->paginate(20)->withQueryString();

        return Inertia::render('Documents/Index', [
            'documents' => $documents->through(fn (Document $doc): array => DocumentResource::summary($doc)),
            'summary' => $summary,
            'filters' => [
                'type' => $request->string('type')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'committee' => $request->string('committee')->toString() ?: null,
                'search' => $request->string('search')->toString() ?: null,
                'processing' => $request->string('processing')->toString() ?: null,
                'trashed' => $request->boolean('trashed'),
                'scope' => $scope,
            ],
            'processingStatuses' => collect(ProcessingStatus::cases())->map(fn (ProcessingStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->all(),
            'documentTypes' => $this->documentTypeOptions(
                $user->can('create', Document::class) ? $references : null,
            ),
            'confidentialityLevels' => collect(Confidentiality::cases())->map(fn (Confidentiality $c): array => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values()->all(),
            'committees' => Committee::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'maxUploadSizeKb' => (int) config('sentria.documents.max_upload_size_kb', 51200),
            'acceptedFileTypes' => $this->acceptedFileExtensions(),
            'openSubmit' => $request->boolean('submit'),
            'can' => [
                'create' => $user->can('create', Document::class),
            ],
        ]);
    }

    /**
     * The figures above the register. They describe the filtered set, so
     * narrowing the filter narrows the counts — a strip that kept reporting
     * the whole corpus while the rows below it were filtered would be lying.
     *
     * @param  Builder<Document>  $query
     * @return array{matching: int, awaiting_action: int, restricted: int, recent: int}
     */
    private function summarize(Builder $query): array
    {
        return [
            'matching' => (clone $query)->toBase()->getCountForPagination(),
            'awaiting_action' => (clone $query)->whereIn('status', $this->awaitingStates())->count(),
            'restricted' => (clone $query)->whereIn('confidentiality', [
                Confidentiality::Confidential->value,
                Confidentiality::Restricted->value,
            ])->count(),
            'recent' => (clone $query)
                ->where('submitted_at', '>=', CarbonImmutable::now()->subDays(30))
                ->count(),
        ];
    }

    /**
     * @return 'matching'|'awaiting'|'restricted'|'recent'
     */
    private function indexScope(Request $request): string
    {
        $scope = $request->string('scope')->toString();

        return in_array($scope, ['awaiting', 'restricted', 'recent'], true)
            ? $scope
            : 'matching';
    }

    /**
     * @param  Builder<Document>  $query
     */
    private function applyIndexScope(Builder $query, string $scope): void
    {
        match ($scope) {
            'awaiting' => $query->whereIn('status', $this->awaitingStates()),
            'restricted' => $query->whereIn('confidentiality', [
                Confidentiality::Confidential->value,
                Confidentiality::Restricted->value,
            ]),
            'recent' => $query->where('submitted_at', '>=', CarbonImmutable::now()->subDays(30)),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function awaitingStates(): array
    {
        return [
            Submitted::$name,
            ReturnedForRevision::$name,
            SecretariatReview::$name,
            CommitteeReferralState::$name,
            CommitteeReview::$name,
            CommitteeReportState::$name,
        ];
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', Document::class);

        return redirect()->route('documents.index', ['submit' => 1]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $actor = $this->requireUser($request);

        $document = $this->versions->createWithUpload(
            $actor,
            [
                'title' => $validated['title'],
                'document_type' => $validated['document_type'],
                'confidentiality' => $validated['confidentiality'] ?? Confidentiality::Internal->value,
                'abstract' => $validated['abstract'] ?? null,
                'external_author' => $validated['external_author'],
                'enacting_clause' => $validated['enacting_clause'] ?? null,
                'explanatory_note' => $validated['explanatory_note'] ?? null,
                'committee_id' => $validated['committee_id'] ?? null,
                'session_id' => $validated['session_id'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'tags' => $validated['tags'] ?? null,
            ],
            $file,
            $validated['change_summary'] ?? null,
        );

        $this->notifier->send(
            User::role(UserRole::Secretariat->value)->where('is_active', true)->get(),
            new DocumentSubmitted($document, $actor),
            $actor,
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.created');
    }

    public function show(Document $document, CommitteeReportNumberAllocator $reportNumbers): Response
    {
        $this->authorize('view', $document);

        $document->load([
            'author',
            'committee',
            'versions.uploader',
            'grants.user',
            'grants.role',
            'grants.committee',
            'referrals.committee',
            'subjectReports.submitter',
            'ordinance',
            'resolution',
            'currentVersion',
        ]);

        $this->audit->record(
            event: 'document.view',
            category: 'documents',
            auditable: $document,
            actor: request()->user(),
            message: 'Document record viewed.',
        );

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $document = $this->voting->syncCompletedThirdReading($document, $user);

        $storedSummary = $this->summaries->loadStoredSummary($document);
        $publication = $document->publications()->latest('updated_at')->first();

        return Inertia::render('Documents/Show', [
            'document' => DocumentResource::detail($document, $user),
            'publication' => $publication ? PublicationResource::summary($publication) : null,
            'history' => array_map(
                fn ($event) => $event->toArray(),
                $this->history->forDocument($document),
            ),
            'can' => [
                'update' => $user->can('update', $document),
                'delete' => $user->can('delete', $document),
                'download' => $user->can('download', $document),
                'uploadVersion' => $user->can('uploadVersion', $document),
                'grantAccess' => $user->can('grantAccess', $document),
                'archive' => $user->can('archive', $document),
                'transition' => $user->can('transition', $document),
                'summarize' => $user->can('ai.summarize') && $user->can('view', $document),
                'related' => $user->can('ai.use') && $user->can('view', $document),
                'compare' => $user->can('ai.compare') && $user->can('view', $document),
                'consistency' => $user->can('ai.checkConsistency') && $user->can('view', $document),
                'createPublication' => $user->can('publications.review')
                    && ! $document->document_type->isMeasure(),
                'createReport' => $user->can('create', CommitteeReport::class),
                'submitReport' => $user->can('reports.submit'),
                'editReferral' => $user->can('documents.refer')
                    && ($document->status instanceof CommitteeReferralState
                        || $document->status instanceof CommitteeReview)
                    && $document->referrals->contains(
                        fn ($referral): bool => in_array($referral->status, ['pending', 'in-review'], true)
                    ),
                'seal' => $user->can('legislation.manage')
                    && $document->sealed_at === null
                    && in_array($document->status->getValue(), ['approved', 'transmittal'], true),
            ],
            'nextReportNumber' => $user->can('create', CommitteeReport::class)
                ? $reportNumbers->preview()
                : null,
            'aiSummary' => $storedSummary?->toMetadataJson(),
            'committees' => Committee::query()
                ->where(function (Builder $query) use ($document): void {
                    $query->where('is_active', true);

                    $referredIds = $document->referrals
                        ->whereNull('completed_at')
                        ->pluck('committee_id')
                        ->filter()
                        ->unique()
                        ->all();

                    if ($referredIds !== []) {
                        $query->orWhereIn('id', $referredIds);
                    } elseif ($document->committee_id !== null) {
                        $query->orWhere('id', $document->committee_id);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'grantable_users' => $user->can('grantAccess', $document)
                ? User::query()
                    ->where('is_active', true)
                    ->orderBy('display_name')
                    ->limit(100)
                    ->get(['id', 'display_name'])
                    ->map(fn (User $grantUser): array => [
                        'id' => $grantUser->getKey(),
                        'display_name' => $grantUser->display_name,
                    ])
                    ->values()
                    ->all()
                : [],
            'grantable_roles' => $user->can('grantAccess', $document)
                ? Role::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Role $role): array => [
                        'id' => $role->getKey(),
                        'name' => $role->name,
                    ])
                    ->values()
                    ->all()
                : [],
        ]);
    }

    public function edit(Document $document): Response
    {
        $this->authorize('update', $document);

        return Inertia::render('Documents/Edit', [
            'document' => DocumentResource::detail($document),
            'documentTypes' => $this->documentTypeOptions(),
            'confidentialityLevels' => collect(Confidentiality::cases())->map(fn (Confidentiality $c): array => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values()->all(),
            'committees' => Committee::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $validated = $request->validated();
        $old = $document->only(['title', 'document_type', 'confidentiality', 'abstract', 'committee_id']);

        $document->update($validated);

        $this->audit->record(
            event: 'document.modify',
            category: 'documents',
            auditable: $document,
            actor: $request->user(),
            old: $old,
            new: $document->only(['title', 'document_type', 'confidentiality', 'abstract', 'committee_id']),
            message: 'Document metadata updated.',
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.updated');
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->delete();

        $this->audit->record(
            event: 'document.delete',
            category: 'documents',
            auditable: $document,
            actor: $request->user(),
            message: 'Document soft-deleted.',
        );

        return redirect()
            ->route('documents.index')
            ->with('success', 'documents.deleted');
    }

    public function restore(Request $request, string $slug): RedirectResponse
    {
        $document = Document::query()->onlyTrashed()->where('slug', $slug)->firstOrFail();
        $this->authorize('restore', $document);

        $document->restore();

        $this->audit->record(
            event: 'document.restore',
            category: 'documents',
            auditable: $document,
            actor: $request->user(),
            message: 'Document restored from soft delete.',
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.restored');
    }

    public function archive(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('archive', $document);

        $this->transitions->transition($document, Archive::class, $this->requireUser($request));
        $document->update(['archived_at' => now()]);

        $this->audit->record(
            event: 'document.archive',
            category: 'documents',
            auditable: $document,
            actor: $request->user(),
            message: 'Document archived.',
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.archived');
    }

    public function seal(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $actor = $this->requireUser($request);
        abort_unless($actor->can('legislation.manage'), 403);
        abort_unless(
            $document->status instanceof Approved || $document->status instanceof Transmittal,
            403,
        );
        abort_if($document->sealed_at !== null, 403);

        $document->forceFill([
            'sealed_at' => now(),
            'sealed_by' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            event: 'document.seal',
            category: 'documents',
            auditable: $document,
            actor: $actor,
            message: 'Sanggunian seal recorded on the measure.',
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'documents.sealed');
    }

    /**
     * @return list<array{value: string, label: string, tag: string, next_reference?: string}>
     */
    private function documentTypeOptions(?DocumentReferenceAllocator $references = null): array
    {
        $previews = $references?->previewAll() ?? [];
        $options = [];

        foreach (DocumentType::cases() as $type) {
            $option = [
                'value' => $type->value,
                'label' => $type->label(),
                'tag' => $type->tag(),
            ];

            if (isset($previews[$type->value])) {
                $option['next_reference'] = $previews[$type->value];
            }

            $options[] = $option;
        }

        return $options;
    }

    /**
     * @return string Comma-separated extensions for the file input `accept` attribute.
     */
    private function acceptedFileExtensions(): string
    {
        $mimeToExtension = [
            'application/pdf' => ['.pdf'],
            'application/msword' => ['.doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['.docx'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['.xlsx'],
            'text/plain' => ['.txt'],
            'image/jpeg' => ['.jpg', '.jpeg'],
            'image/png' => ['.png'],
            'image/tiff' => ['.tif', '.tiff'],
        ];

        /** @var list<string> $allowed */
        $allowed = config('sentria.documents.allowed_mime_types', []);

        return collect($allowed)
            ->flatMap(fn (string $mime): array => $mimeToExtension[$mime] ?? [])
            ->unique()
            ->implode(',');
    }
}
