<?php

namespace App\Http\Controllers;

use App\Http\Requests\Publications\PublicationTransitionRequest;
use App\Http\Requests\Publications\StorePublicationRequest;
use App\Http\Resources\PublicationResource;
use App\Models\Document;
use App\Models\Publication;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Publication\InternalDocument;
use App\States\Publication\MarkPublic;
use App\States\Publication\PublicationReview;
use App\States\Publication\Published;
use App\States\Publication\SecretariatReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicationController extends Controller
{
    public function __construct(private readonly GuardedStateTransition $transitions) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Publication::class);

        $query = $this->queueQuery($request);
        $summary = $this->summarizeIndex($query);
        $scope = $this->indexScope($request);
        $status = $request->string('status')->toString();

        if ($status !== '') {
            $query->where('status', $status);
        } else {
            $this->applyIndexScope($query, $scope);
        }

        $paginator = $query->paginate(20)->withQueryString();

        return Inertia::render('Publications/Index', [
            'publications' => $paginator->through(
                fn (Publication $publication): array => PublicationResource::summary($publication)
            ),
            'summary' => $summary,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $status !== '' ? $status : null,
                'scope' => $scope,
            ],
            'statuses' => $this->statusOptions(),
            'can' => [
                'create' => $request->user()?->can('create', Publication::class) ?? false,
            ],
        ]);
    }

    public function show(Request $request, Publication $publication): Response
    {
        $this->authorize('view', $publication);
        $this->loadDetailRelations($publication);

        return Inertia::render('Publications/Show', [
            'publication' => PublicationResource::detail($publication),
            'can' => [
                'create' => $request->user()?->can('create', Publication::class) ?? false,
                'transition' => $request->user()?->can('transition', $publication) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Publication::class);

        $documents = Document::query()
            ->whereDoesntHave('publications', function (Builder $query): void {
                $query->whereNull('unpublished_at');
            })
            ->withoutPendingLegislation()
            ->latest()
            ->limit(100)
            ->get(['id', 'slug', 'title', 'reference_number', 'abstract']);

        return Inertia::render('Publications/Create', [
            'documents' => $documents->map(fn (Document $document): array => [
                'id' => $document->getKey(),
                'slug' => $document->slug,
                'title' => $document->title,
                'reference_number' => $document->reference_number,
                'abstract' => $document->abstract,
            ])->values()->all(),
        ]);
    }

    public function store(StorePublicationRequest $request): RedirectResponse
    {
        $document = $request->document();

        $existing = Publication::query()
            ->where('document_id', $document->getKey())
            ->whereNull('unpublished_at')
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('publications.show', $existing)
                ->with('success', 'publications.already_exists');
        }

        $title = $request->validated('title');
        $publication = Publication::query()->create([
            'document_id' => $document->getKey(),
            'document_version_id' => $document->currentVersion?->getKey(),
            'status' => InternalDocument::$name,
            'public_slug' => $this->uniqueSlug($title),
            'title' => $title,
            'summary' => $request->validated('summary'),
            'categories' => $request->validated('categories') ?? [],
        ]);

        return redirect()
            ->route('publications.show', $publication)
            ->with('success', 'publications.created');
    }

    public function createFromDocument(Document $document): RedirectResponse
    {
        $this->authorize('create', Publication::class);

        if ($document->awaitsLegislationRecord()) {
            return back()->with('error', 'publications.requires_legislation_record');
        }

        $existing = Publication::query()
            ->where('document_id', $document->getKey())
            ->whereNull('unpublished_at')
            ->first();

        if ($existing !== null) {
            return redirect()->route('publications.show', $existing);
        }

        $publication = Publication::query()->create([
            'document_id' => $document->getKey(),
            'document_version_id' => $document->currentVersion?->getKey(),
            'status' => InternalDocument::$name,
            'public_slug' => $this->uniqueSlug($document->title),
            'title' => $document->title,
            'summary' => $document->abstract,
            'categories' => [],
        ]);

        return redirect()
            ->route('publications.show', $publication)
            ->with('success', 'publications.created');
    }

    public function transition(PublicationTransitionRequest $request, Publication $publication): RedirectResponse
    {
        $this->authorize('transition', $publication);

        $this->transitions->transition(
            $publication,
            $request->targetStateClass(),
            $this->requireUser($request),
        );

        return redirect()
            ->route('publications.show', $publication)
            ->with('success', 'publications.transitioned');
    }

    /**
     * @return Builder<Publication>
     */
    private function queueQuery(Request $request): Builder
    {
        $query = Publication::query()
            ->with(['document.ordinance', 'document.resolution', 'reviewer', 'publisher'])
            ->latest('updated_at');

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';

            $query->where(function ($inner) use ($term): void {
                $inner->where('title', 'ilike', $term)
                    ->orWhere('public_slug', 'ilike', $term)
                    ->orWhereHas('document', function ($document) use ($term): void {
                        $document->where('title', 'ilike', $term)
                            ->orWhere('reference_number', 'ilike', $term);
                    });
            });
        }

        return $query;
    }

    /**
     * Counted before the card scope so the four figures stay about the finding
     * aid, not about the subset the reader just clicked into.
     *
     * @param  Builder<Publication>  $query
     * @return array{matching: int, drafting: int, review: int, published: int}
     */
    private function summarizeIndex(Builder $query): array
    {
        return [
            'matching' => (clone $query)->count(),
            'drafting' => (clone $query)->where('status', InternalDocument::$name)->count(),
            'review' => (clone $query)->whereIn('status', [
                SecretariatReview::$name,
                PublicationReview::$name,
            ])->count(),
            'published' => (clone $query)->whereIn('status', [
                MarkPublic::$name,
                Published::$name,
            ])->count(),
        ];
    }

    private function indexScope(Request $request): string
    {
        $scope = $request->string('scope')->toString();

        return in_array($scope, ['drafting', 'review', 'published'], true) ? $scope : 'matching';
    }

    /**
     * @param  Builder<Publication>  $query
     */
    private function applyIndexScope(Builder $query, string $scope): void
    {
        match ($scope) {
            'drafting' => $query->where('status', InternalDocument::$name),
            'review' => $query->whereIn('status', [
                SecretariatReview::$name,
                PublicationReview::$name,
            ]),
            'published' => $query->whereIn('status', [
                MarkPublic::$name,
                Published::$name,
            ]),
            default => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => InternalDocument::$name, 'label' => 'Internal Document'],
            ['value' => SecretariatReview::$name, 'label' => 'Secretariat Review'],
            ['value' => PublicationReview::$name, 'label' => 'Publication Review'],
            ['value' => MarkPublic::$name, 'label' => 'Mark Public'],
            ['value' => Published::$name, 'label' => 'Published'],
        ];
    }

    private function loadDetailRelations(Publication $publication): void
    {
        $publication->loadMissing(['document.ordinance', 'document.resolution', 'reviewer', 'publisher']);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 60, ''));

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Publication::query()->where('public_slug', $slug)->exists());

        return $slug;
    }
}
