<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\LegislationKind;
use App\Http\Requests\Legislation\StoreOrdinanceRequest;
use App\Http\Requests\Legislation\UpdateOrdinanceRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PublicationResource;
use App\Models\Document;
use App\Models\Ordinance;
use App\Models\Publication;
use App\Services\Legislation\LegislationNumberAllocator;
use App\Services\Legislation\LegislativeHistoryService;
use App\States\Publication\Published;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrdinanceController extends Controller
{
    public function __construct(
        private readonly LegislativeHistoryService $history,
        private readonly LegislationNumberAllocator $numbers,
    ) {}

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
            'nextNumber' => $this->numbers->preview(LegislationKind::Ordinance),
            'seriesYear' => (int) now()->year,
        ]);
    }

    public function store(StoreOrdinanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $number = trim((string) ($validated['ordinance_number'] ?? ''));

        $ordinance = DB::transaction(fn (): Ordinance => Ordinance::query()->create([
            ...$validated,
            'ordinance_number' => $number !== ''
                ? $number
                : $this->numbers->allocate(LegislationKind::Ordinance),
            'series_year' => (int) now()->year,
        ]));

        return redirect()
            ->route('ordinances.show', $ordinance)
            ->with('success', 'legislation.ordinance_created');
    }

    public function show(Ordinance $ordinance): Response
    {
        $this->authorize('view', $ordinance);
        $ordinance->load([
            'document.publications.document',
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
            'signedCopyRequiresConfirmation' => $ordinance->document?->publications
                ->contains(fn (Publication $publication): bool => $publication->status instanceof Published) ?? false,
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

        return redirect()
            ->route('ordinances.show', $ordinance)
            ->with('success', 'legislation.ordinance_updated');
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
