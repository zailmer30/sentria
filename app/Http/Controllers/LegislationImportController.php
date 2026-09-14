<?php

namespace App\Http\Controllers;

use App\Enums\LegislationKind;
use App\Http\Requests\Legislation\StoreLegislationImportRequest;
use App\Models\LegislationImportBatch;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Services\Legislation\LegislationImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

class LegislationImportController extends Controller
{
    public function __construct(private readonly LegislationImportService $imports) {}

    public function createOrdinance(): InertiaResponse
    {
        return $this->create(LegislationKind::Ordinance);
    }

    public function createResolution(): InertiaResponse
    {
        return $this->create(LegislationKind::Resolution);
    }

    public function templateOrdinance(): Response
    {
        $this->authorize('create', Ordinance::class);

        return $this->template(LegislationKind::Ordinance);
    }

    public function templateResolution(): Response
    {
        $this->authorize('create', Resolution::class);

        return $this->template(LegislationKind::Resolution);
    }

    public function storeOrdinance(StoreLegislationImportRequest $request): RedirectResponse
    {
        return $this->store($request, LegislationKind::Ordinance);
    }

    public function storeResolution(StoreLegislationImportRequest $request): RedirectResponse
    {
        return $this->store($request, LegislationKind::Resolution);
    }

    public function showOrdinance(LegislationImportBatch $batch): InertiaResponse
    {
        return $this->show($batch, LegislationKind::Ordinance);
    }

    public function showResolution(LegislationImportBatch $batch): InertiaResponse
    {
        return $this->show($batch, LegislationKind::Resolution);
    }

    public function commitOrdinance(Request $request, LegislationImportBatch $batch): RedirectResponse
    {
        return $this->commit($request, $batch, LegislationKind::Ordinance);
    }

    public function commitResolution(Request $request, LegislationImportBatch $batch): RedirectResponse
    {
        return $this->commit($request, $batch, LegislationKind::Resolution);
    }

    private function create(LegislationKind $kind): InertiaResponse
    {
        $this->authorizeCreate($kind);

        return Inertia::render('Legislation/Import', [
            'kind' => $kind->value,
            'batch' => null,
            'preview' => null,
            'result' => null,
            ...$this->importLimits(),
            'templateUrl' => $kind === LegislationKind::Ordinance
                ? route('ordinances.import.template')
                : route('resolutions.import.template'),
            'storeUrl' => $kind === LegislationKind::Ordinance
                ? route('ordinances.import.store')
                : route('resolutions.import.store'),
            'indexUrl' => $kind === LegislationKind::Ordinance
                ? route('ordinances.index')
                : route('resolutions.index'),
        ]);
    }

    private function store(StoreLegislationImportRequest $request, LegislationKind $kind): RedirectResponse
    {
        $this->authorizeCreate($kind);

        $csv = $request->file('csv');
        $zip = $request->file('zip');

        abort_unless($csv !== null && $zip !== null, Response::HTTP_UNPROCESSABLE_ENTITY);

        $batch = $this->imports->start($this->requireUser($request), $kind, $csv, $zip);

        $show = $kind === LegislationKind::Ordinance
            ? route('ordinances.import.show', $batch)
            : route('resolutions.import.show', $batch);

        return redirect()->to($show);
    }

    private function show(LegislationImportBatch $batch, LegislationKind $kind): InertiaResponse
    {
        $this->authorizeCreate($kind);
        $this->assertKind($batch, $kind);

        $preview = is_array($batch->preview) ? $batch->preview : $this->imports->preview($batch)->toArray();

        return Inertia::render('Legislation/Import', [
            'kind' => $kind->value,
            'batch' => [
                'id' => $batch->getKey(),
                'status' => $batch->status,
                'csv_filename' => $batch->csv_filename,
                'zip_filename' => $batch->zip_filename,
                'committed_at' => $batch->committed_at?->toIso8601String(),
            ],
            'preview' => $preview,
            'result' => $batch->result,
            ...$this->importLimits(),
            'templateUrl' => $kind === LegislationKind::Ordinance
                ? route('ordinances.import.template')
                : route('resolutions.import.template'),
            'storeUrl' => $kind === LegislationKind::Ordinance
                ? route('ordinances.import.store')
                : route('resolutions.import.store'),
            'commitUrl' => $kind === LegislationKind::Ordinance
                ? route('ordinances.import.commit', $batch)
                : route('resolutions.import.commit', $batch),
            'indexUrl' => $kind === LegislationKind::Ordinance
                ? route('ordinances.index')
                : route('resolutions.index'),
        ]);
    }

    private function commit(Request $request, LegislationImportBatch $batch, LegislationKind $kind): RedirectResponse
    {
        $this->authorizeCreate($kind);
        $this->assertKind($batch, $kind);

        try {
            $this->imports->commit($batch, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->back()
                ->with('error', $exception->getMessage());
        }

        $show = $kind === LegislationKind::Ordinance
            ? route('ordinances.import.show', $batch)
            : route('resolutions.import.show', $batch);

        return redirect()
            ->to($show)
            ->with('success', 'legislation.import_committed');
    }

    private function template(LegislationKind $kind): Response
    {
        $filename = $kind->value.'-import-template.csv';
        $csv = $this->imports->templateCsv($kind);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array{maxCsvKb: int, maxZipKb: int}
     */
    private function importLimits(): array
    {
        return [
            'maxCsvKb' => (int) config('sentria.legislation.import_csv_max_kb', 2048),
            'maxZipKb' => (int) config('sentria.legislation.import_zip_max_kb', 204800),
        ];
    }

    private function authorizeCreate(LegislationKind $kind): void
    {
        $this->authorize('create', $kind === LegislationKind::Ordinance ? Ordinance::class : Resolution::class);
    }

    private function assertKind(LegislationImportBatch $batch, LegislationKind $kind): void
    {
        abort_unless($batch->kind === $kind, 404);
    }
}
