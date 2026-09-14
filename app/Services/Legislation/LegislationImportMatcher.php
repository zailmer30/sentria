<?php

namespace App\Services\Legislation;

use App\DTO\Legislation\LegislationImportCsvRow;
use App\DTO\Legislation\LegislationImportPreview;
use App\DTO\Legislation\LegislationImportZipEntry;
use App\Enums\LegislationKind;
use App\Models\Ordinance;
use App\Models\Resolution;

class LegislationImportMatcher
{
    public function __construct(private readonly LegislationCitationNormalizer $normalizer) {}

    /**
     * @param  list<LegislationImportCsvRow>  $rows
     * @param  list<LegislationImportZipEntry>  $files
     */
    public function match(LegislationKind $kind, array $rows, array $files): LegislationImportPreview
    {
        $existing = $this->existingKeys($kind);
        $filesByKey = [];
        $unmatchedFiles = [];
        $claimedFiles = [];

        foreach ($files as $file) {
            if ($file->key === null) {
                $unmatchedFiles[] = [
                    'filename' => $file->basename,
                    'entry' => $file->entryName,
                    'reason' => 'unparsed',
                ];

                continue;
            }

            $key = $file->key->value();

            if (isset($filesByKey[$key])) {
                $unmatchedFiles[] = [
                    'filename' => $file->basename,
                    'entry' => $file->entryName,
                    'reason' => 'duplicate_file',
                    'key' => $key,
                ];

                continue;
            }

            $filesByKey[$key] = $file;
        }

        $matched = [];
        $unmatchedRows = [];
        $duplicates = [];
        $invalid = [];

        foreach ($rows as $row) {
            if (! $row->isValid() || $row->key === null) {
                $invalid[] = [
                    'line' => $row->line,
                    'number' => $row->number,
                    'title' => $row->title,
                    'reason' => $row->error ?? 'invalid',
                ];

                continue;
            }

            $key = $row->key->value();

            if (isset($existing[$key])) {
                $duplicates[] = [
                    'line' => $row->line,
                    'number' => $row->number,
                    'series_year' => $row->year,
                    'title' => $row->title,
                    'existing_number' => $existing[$key],
                    'filename' => $filesByKey[$key]->basename ?? null,
                    'key' => $key,
                ];

                if (isset($filesByKey[$key])) {
                    $claimedFiles[$key] = true;
                }

                continue;
            }

            if (! isset($filesByKey[$key])) {
                $unmatchedRows[] = [
                    'line' => $row->line,
                    'number' => $row->number,
                    'series_year' => $row->year,
                    'title' => $row->title,
                    'reason' => 'no_file',
                    'key' => $key,
                ];

                continue;
            }

            $file = $filesByKey[$key];
            $claimedFiles[$key] = true;

            $matched[] = [
                'key' => $key,
                'line' => $row->line,
                'number' => $row->number,
                'series_year' => $row->year,
                'title' => $row->title,
                'status' => $row->status,
                'filename' => $file->basename,
                'entry' => $file->entryName,
                'fields' => $row->fields,
            ];
        }

        foreach ($filesByKey as $key => $file) {
            if (isset($claimedFiles[$key])) {
                continue;
            }

            $unmatchedFiles[] = [
                'filename' => $file->basename,
                'entry' => $file->entryName,
                'reason' => 'no_row',
                'key' => $key,
            ];
        }

        return new LegislationImportPreview(
            matched: $matched,
            unmatchedRows: $unmatchedRows,
            unmatchedFiles: $unmatchedFiles,
            duplicates: $duplicates,
            invalid: $invalid,
        );
    }

    /**
     * @return array<string, string>
     */
    private function existingKeys(LegislationKind $kind): array
    {
        $existing = [];

        if ($kind === LegislationKind::Ordinance) {
            foreach (Ordinance::query()->get(['ordinance_number', 'series_year']) as $ordinance) {
                $key = $this->normalizer->fromRegister(
                    $kind,
                    (string) $ordinance->ordinance_number,
                    (int) $ordinance->series_year,
                );

                if ($key !== null) {
                    $existing[$key->value()] = (string) $ordinance->ordinance_number;
                }
            }

            return $existing;
        }

        foreach (Resolution::query()->get(['resolution_number', 'series_year']) as $resolution) {
            $key = $this->normalizer->fromRegister(
                $kind,
                (string) $resolution->resolution_number,
                (int) $resolution->series_year,
            );

            if ($key !== null) {
                $existing[$key->value()] = (string) $resolution->resolution_number;
            }
        }

        return $existing;
    }
}
