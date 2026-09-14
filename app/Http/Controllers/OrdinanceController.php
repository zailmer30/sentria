<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\Legislation\StoreOrdinanceRequest;
use App\Http\Requests\Legislation\UpdateOrdinanceRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PublicationResource;
use App\Models\Document;
use App\Models\Ordinance;
use App\Services\Legislation\LegislativeHistoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrdinanceController extends Controller
{
    public function __construct(private readonly LegislativeHistoryService $history) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Ordinance::class);

        $query = Ordinance::query()
            ->with(['document.currentVersion', 'document.author', 'document.committee'])
            ->latest('series_year')
            ->latest('ordinance_number');

        $this->filterIndex($query, $request);

        $summary = $this->summarizeIndex($query);

        $ordinances = $query->paginate(20)->withQueryString();

        $years = Ordinance::query()
            ->select('series_year')
            ->distinct()
            ->orderByDesc('series_year')
            ->pluck('series_year')
            ->all();

        return Inertia::render('Legislation/Ordinances/Index', [
            'ordinances' => $ordinances->through(
                fn (Ordinance $o): array => DocumentResource::ordinance($o)
            ),
            'summary' => $summary,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'year' => $request->filled('year') ? $request->integer('year') : null,
            ],
            'statuses' => ['draft', 'pending', 'enacted', 'vetoed', 'repealed'],
            'years' => $years,
            'can' => [
                'create' => $request->user()?->can('create', Ordinance::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Ordinance::class);

        return Inertia::render('Legislation/Ordinances/Form', [
            'ordinance' => null,
            'documents' => Document::linkableOfTypes(DocumentType::ordinanceMeasures()),
        ]);
    }

    public function store(StoreOrdinanceRequest $request): RedirectResponse
    {
        $ordinance = Ordinance::query()->create($request->validated());
        $this->applyDefaultEffectivity($ordinance);

        return redirect()
            ->route('ordinances.show', $ordinance)
            ->with('success', 'legislation.ordinance_created');
    }

    public function show(Ordinance $ordinance): Response
    {
        $this->authorize('view', $ordinance);
        $ordinance->load([
            'document.publications',
            'document.currentVersion',
            'document.author',
            'document.committee',
            'signedCopyUploader',
        ]);

        $history = $ordinance->document
            ? array_map(
                fn ($event) => $event->toArray(),
                $this->history->forDocument($ordinance->document),
            )
            : [];

        return Inertia::render('Legislation/Ordinances/Show', [
            'ordinance' => DocumentResource::ordinance($ordinance),
            'publication' => ($publication = $ordinance->document?->publications->sortByDesc('updated_at')->first())
                ? PublicationResource::summary($publication)
                : null,
            'history' => $history,
            'can' => [
                'update' => request()->user()?->can('update', $ordinance) ?? false,
                'createPublication' => request()->user()?->can('publications.review') ?? false,
            ],
        ]);
    }

    public function edit(Ordinance $ordinance): Response
    {
        $this->authorize('update', $ordinance);
        $ordinance->load(['document.currentVersion', 'document.author', 'document.committee']);

        return Inertia::render('Legislation/Ordinances/Form', [
            'ordinance' => DocumentResource::ordinance($ordinance),
            'documents' => Document::linkableOfTypes(DocumentType::ordinanceMeasures(), $ordinance->document_id),
        ]);
    }

    public function update(UpdateOrdinanceRequest $request, Ordinance $ordinance): RedirectResponse
    {
        $ordinance->update($request->validated());
        $this->applyDefaultEffectivity($ordinance);

        return redirect()
            ->route('ordinances.show', $ordinance)
            ->with('success', 'legislation.ordinance_updated');
    }

    private function applyDefaultEffectivity(Ordinance $ordinance): void
    {
        $ordinance->refresh();

        if ($ordinance->effectivity_date !== null || $ordinance->publication_date === null) {
            return;
        }

        $ordinance->loadMissing('document');

        $ordinance->forceFill([
            'effectivity_date' => $ordinance->publication_date->copy()->addDays(
                Ordinance::daysUntilEffectivity($ordinance->document?->proposed_effectivity),
            ),
        ])->save();
    }

    /**
     * @param  Builder<Ordinance>  $query
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
                    ->orWhere('ordinance_number', 'ilike', '%'.$search.'%')
                    ->orWhere('purpose', 'ilike', '%'.$search.'%');
            });
        }
    }

    /**
     * @param  Builder<Ordinance>  $query
     * @return array{matching: int, draft: int, pending: int, enacted: int}
     */
    private function summarizeIndex(Builder $query): array
    {
        return [
            'matching' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'enacted' => (clone $query)->where('status', 'enacted')->count(),
        ];
    }
}
