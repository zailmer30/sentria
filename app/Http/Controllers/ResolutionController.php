<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\LegislationKind;
use App\Http\Requests\Legislation\StoreResolutionRequest;
use App\Http\Requests\Legislation\UpdateResolutionRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PublicationResource;
use App\Models\Document;
use App\Models\Publication;
use App\Models\Resolution;
use App\Services\Legislation\LegislationNumberAllocator;
use App\Services\Legislation\LegislativeHistoryService;
use App\States\Publication\Published;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ResolutionController extends Controller
{
    public function __construct(
        private readonly LegislativeHistoryService $history,
        private readonly LegislationNumberAllocator $numbers,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Resolution::class);

        $query = Resolution::query()
            ->with(['document.currentVersion', 'document.author', 'document.committee'])
            ->latest('series_year')
            ->latest('resolution_number');

        $this->filterIndex($query, $request);

        $summary = $this->summarizeIndex($query);

        $resolutions = $query->paginate(20)->withQueryString();

        $years = Resolution::query()
            ->select('series_year')
            ->distinct()
            ->orderByDesc('series_year')
            ->pluck('series_year')
            ->all();

        return Inertia::render('Legislation/Resolutions/Index', [
            'resolutions' => $resolutions->through(
                fn (Resolution $r): array => DocumentResource::resolution($r)
            ),
            'summary' => $summary,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'year' => $request->filled('year') ? $request->integer('year') : null,
            ],
            'statuses' => ['draft', 'pending', 'adopted', 'withdrawn'],
            'years' => $years,
            'can' => [
                'create' => $request->user()?->can('create', Resolution::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Resolution::class);

        return Inertia::render('Legislation/Resolutions/Form', [
            'resolution' => null,
            'documents' => Document::linkableOfTypes(DocumentType::resolutionMeasures()),
            'nextNumber' => $this->numbers->preview(LegislationKind::Resolution),
            'seriesYear' => (int) now()->year,
        ]);
    }

    public function store(StoreResolutionRequest $request): RedirectResponse
    {
        $resolution = DB::transaction(fn (): Resolution => Resolution::query()->create([
            ...$request->validated(),
            'resolution_number' => $this->numbers->allocate(LegislationKind::Resolution),
            'series_year' => (int) now()->year,
        ]));

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', 'legislation.resolution_created');
    }

    public function show(Resolution $resolution): Response
    {
        $this->authorize('view', $resolution);
        $resolution->load([
            'document.publications.document',
            'document.currentVersion',
            'document.author',
            'document.committee',
            'signedCopyUploader',
        ]);

        $history = $resolution->document
            ? array_map(
                fn ($event) => $event->toArray(),
                $this->history->forDocument($resolution->document),
            )
            : [];

        return Inertia::render('Legislation/Resolutions/Show', [
            'resolution' => DocumentResource::resolution($resolution),
            'publication' => ($publication = $resolution->document?->publications->sortByDesc('updated_at')->first())
                ? PublicationResource::summary($publication)
                : null,
            'history' => $history,
            'can' => [
                'update' => request()->user()?->can('update', $resolution) ?? false,
                'createPublication' => request()->user()?->can('publications.review') ?? false,
            ],
            'signedCopyRequiresConfirmation' => $resolution->document?->publications
                ->contains(fn (Publication $publication): bool => $publication->status instanceof Published) ?? false,
        ]);
    }

    public function edit(Resolution $resolution): Response
    {
        $this->authorize('update', $resolution);
        $resolution->load(['document.currentVersion', 'document.author', 'document.committee']);

        return Inertia::render('Legislation/Resolutions/Form', [
            'resolution' => DocumentResource::resolution($resolution),
            'documents' => Document::linkableOfTypes(DocumentType::resolutionMeasures(), $resolution->document_id),
        ]);
    }

    public function update(UpdateResolutionRequest $request, Resolution $resolution): RedirectResponse
    {
        $resolution->update($request->validated());

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('success', 'legislation.resolution_updated');
    }

    /**
     * @param  Builder<Resolution>  $query
     */
    private function filterIndex(Builder $query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('year')) {
            $query->where('series_year', $request->integer('year'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('title', 'ilike', '%'.$search.'%')
                    ->orWhere('resolution_number', 'ilike', '%'.$search.'%')
                    ->orWhere('purpose', 'ilike', '%'.$search.'%');
            });
        }
    }

    /**
     * @param  Builder<Resolution>  $query
     * @return array{matching: int, draft: int, pending: int, adopted: int}
     */
    private function summarizeIndex(Builder $query): array
    {
        return [
            'matching' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'adopted' => (clone $query)->where('status', 'adopted')->count(),
        ];
    }
}
