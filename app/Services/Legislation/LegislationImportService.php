<?php

namespace App\Services\Legislation;

use App\DTO\Legislation\LegislationImportPreview;
use App\Enums\Confidentiality;
use App\Enums\LegislationKind;
use App\Models\LegislationImportBatch;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentVersionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class LegislationImportService
{
    public function __construct(
        private readonly LegislationImportCsvParser $csv,
        private readonly LegislationImportZipReader $zip,
        private readonly LegislationImportMatcher $matcher,
        private readonly DocumentVersionService $versions,
        private readonly LegislativeSignedCopyService $signedCopies,
        private readonly AuditLogger $audit,
    ) {}

    public function start(
        User $actor,
        LegislationKind $kind,
        UploadedFile $csvFile,
        UploadedFile $zipFile,
    ): LegislationImportBatch {
        $batch = LegislationImportBatch::query()->create([
            'kind' => $kind,
            'status' => 'previewed',
            'uploaded_by' => $actor->getKey(),
            'disk' => 'local',
            'csv_path' => '',
            'csv_filename' => $csvFile->getClientOriginalName(),
            'zip_path' => '',
            'zip_filename' => $zipFile->getClientOriginalName(),
        ]);

        $folder = 'legislation-imports/'.$batch->getKey();
        $csvPath = $folder.'/register.csv';
        $zipPath = $folder.'/files.zip';

        Storage::disk('local')->putFileAs($folder, $csvFile, 'register.csv');
        Storage::disk('local')->putFileAs($folder, $zipFile, 'files.zip');

        $batch->forceFill([
            'csv_path' => $csvPath,
            'zip_path' => $zipPath,
        ])->save();

        $preview = $this->preview($batch);

        $batch->forceFill([
            'preview' => $preview->toArray(),
        ])->save();

        $this->audit->record(
            event: 'legislation.import.preview',
            category: 'legislation',
            auditable: $batch,
            actor: $actor,
            new: $preview->toArray()['counts'],
            message: 'Historical legislation import previewed.',
        );

        return $batch->refresh();
    }

    public function preview(LegislationImportBatch $batch): LegislationImportPreview
    {
        $csvPath = Storage::disk($batch->disk)->path($batch->csv_path);
        $zipPath = Storage::disk($batch->disk)->path($batch->zip_path);

        $rows = $this->csv->parse($csvPath, $batch->kind);
        $files = $this->zip->list($zipPath, $batch->kind);

        return $this->matcher->match($batch->kind, $rows, $files);
    }

    /**
     * @return array{created: int, skipped: int, failed: list<array<string, mixed>>}
     */
    public function commit(LegislationImportBatch $batch, User $actor): array
    {
        if ($batch->isCommitted()) {
            throw new InvalidArgumentException('This import has already been committed.');
        }

        $preview = $this->preview($batch);
        $extracted = $this->zip->extract(
            Storage::disk($batch->disk)->path($batch->zip_path),
            $batch->disk,
            'legislation-imports/'.$batch->getKey().'/extracted',
        );

        $created = 0;
        $failed = [];

        foreach ($preview->matched as $pair) {
            $entry = (string) $pair['entry'];
            $relative = $extracted[$entry] ?? null;

            if ($relative === null) {
                $failed[] = [
                    'number' => $pair['number'],
                    'reason' => 'missing_file',
                ];

                continue;
            }

            try {
                DB::transaction(function () use ($batch, $actor, $pair, $relative): void {
                    $this->commitPair($batch, $actor, $pair, $relative);
                });
                $created++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'number' => $pair['number'],
                    'reason' => 'commit_failed',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $result = [
            'created' => $created,
            'skipped' => count($preview->duplicates) + count($preview->unmatchedRows) + count($preview->invalid),
            'failed' => $failed,
            'counts' => $preview->toArray()['counts'],
        ];

        $batch->forceFill([
            'status' => 'committed',
            'committed_at' => now(),
            'preview' => $preview->toArray(),
            'result' => $result,
        ])->save();

        $this->audit->record(
            event: 'legislation.import.commit',
            category: 'legislation',
            auditable: $batch,
            actor: $actor,
            new: [
                'created' => $created,
                'failed' => count($failed),
            ],
            message: 'Historical legislation import committed.',
        );

        return $result;
    }

    /**
     * @return list<string>
     */
    public function templateHeaders(LegislationKind $kind): array
    {
        return match ($kind) {
            LegislationKind::Ordinance => [
                'ordinance_number',
                'series_year',
                'title',
                'status',
                'purpose',
                'enacted_on',
                'effectivity_date',
                'approving_authority',
            ],
            LegislationKind::Resolution => [
                'resolution_number',
                'series_year',
                'title',
                'status',
                'purpose',
                'category',
                'adopted_on',
                'effectivity_date',
                'transmitted_on',
                'transmitted_to',
            ],
        };
    }

    public function templateCsv(LegislationKind $kind): string
    {
        $headers = $this->templateHeaders($kind);
        $example = match ($kind) {
            LegislationKind::Ordinance => [
                '012',
                '2019',
                'Provincial Scholarship Ordinance',
                'enacted',
                '',
                '2019-03-12',
                '',
                '',
            ],
            LegislationKind::Resolution => [
                '045',
                '2020',
                'Commendation Resolution',
                'adopted',
                '',
                '',
                '2020-06-01',
                '',
                '',
                '',
            ],
        };

        return $this->toCsv([$headers, $example]);
    }

    /**
     * @param  array<string, mixed>  $pair
     */
    private function commitPair(LegislationImportBatch $batch, User $actor, array $pair, string $relativePath): void
    {
        $absolutePath = Storage::disk($batch->disk)->path($relativePath);
        $kind = $batch->kind;
        $fields = $pair['fields'];
        $safeName = sprintf('%s-%d-%d.pdf', $kind->value, $pair['series_year'], $this->sequenceFromKey((string) $pair['key']));
        $upload = new UploadedFile($absolutePath, $safeName, 'application/pdf', UPLOAD_ERR_OK, true);

        $document = $this->versions->createBackfile(
            $actor,
            [
                'title' => (string) $fields['title'],
                'document_type' => $kind->documentType()->value,
                'confidentiality' => Confidentiality::Internal->value,
                'abstract' => $fields['purpose'] ?? null,
                'reference_year' => (int) $pair['series_year'],
            ],
            $upload,
            sprintf('Imported historical record (%s).', $pair['filename']),
        );

        if ($kind === LegislationKind::Ordinance) {
            $record = Ordinance::query()->create($this->ordinanceAttributes($document->getKey(), $actor, $fields));
        } else {
            $record = Resolution::query()->create($this->resolutionAttributes($document->getKey(), $actor, $fields));
        }

        $this->signedCopies->storeFromPath($record, $actor, $absolutePath, $safeName);

        $this->audit->record(
            event: 'legislation.import.record',
            category: 'legislation',
            auditable: $record,
            actor: $actor,
            new: [
                'number' => $pair['number'],
                'filename' => $pair['filename'],
                'document_id' => $document->getKey(),
            ],
            message: 'Historical legislative record imported.',
        );
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return array<string, mixed>
     */
    private function ordinanceAttributes(string $documentId, User $actor, array $fields): array
    {
        return [
            'document_id' => $documentId,
            'ordinance_number' => $fields['ordinance_number'],
            'series_year' => (int) $fields['series_year'],
            'title' => $fields['title'],
            'purpose' => $fields['purpose'],
            'status' => $fields['status'],
            'enacted_on' => $fields['enacted_on'],
            'approving_authority' => $fields['approving_authority'],
            'effectivity_date' => $fields['effectivity_date'],
            'imported_at' => now(),
            'imported_by' => $actor->getKey(),
        ];
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return array<string, mixed>
     */
    private function resolutionAttributes(string $documentId, User $actor, array $fields): array
    {
        return [
            'document_id' => $documentId,
            'resolution_number' => $fields['resolution_number'],
            'series_year' => (int) $fields['series_year'],
            'title' => $fields['title'],
            'purpose' => $fields['purpose'],
            'category' => $fields['category'],
            'status' => $fields['status'],
            'adopted_on' => $fields['adopted_on'],
            'effectivity_date' => $fields['effectivity_date'],
            'transmitted_on' => $fields['transmitted_on'],
            'transmitted_to' => $fields['transmitted_to'],
            'imported_at' => now(),
            'imported_by' => $actor->getKey(),
        ];
    }

    private function sequenceFromKey(string $key): int
    {
        $parts = explode(':', $key);

        return (int) ($parts[2] ?? 0);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+b');

        if ($handle === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
