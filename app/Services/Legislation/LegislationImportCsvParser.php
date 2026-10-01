<?php

namespace App\Services\Legislation;

use App\DTO\Legislation\LegislationImportCsvRow;
use App\Enums\LegislationKind;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class LegislationImportCsvParser
{
    public function __construct(private readonly LegislationCitationNormalizer $normalizer) {}

    /**
     * @return list<LegislationImportCsvRow>
     */
    public function parse(string $absolutePath, LegislationKind $kind): array
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('The CSV file could not be read.');
        }

        try {
            $headerLine = fgets($handle);

            if ($headerLine === false) {
                throw new InvalidArgumentException('The CSV file is empty.');
            }

            $headerLine = $this->stripBom($headerLine);
            $headers = $this->normalizeHeaders(str_getcsv($headerLine));
            $numberHeader = $kind->numberColumn();

            if (! in_array($numberHeader, $headers, true) || ! in_array('series_year', $headers, true) || ! in_array('title', $headers, true) || ! in_array('status', $headers, true)) {
                throw new InvalidArgumentException(
                    "The CSV must include {$numberHeader}, series_year, title, and status columns.",
                );
            }

            $rows = [];
            $line = 1;
            $seenKeys = [];

            while (($raw = fgetcsv($handle)) !== false) {
                $line++;

                if ($this->isEmptyRow($raw)) {
                    continue;
                }

                $mapped = $this->mapRow($headers, $raw);
                $rows[] = $this->hydrate($kind, $line, $mapped, $numberHeader, $seenKeys);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $raw
     * @return array<string, string>
     */
    private function mapRow(array $headers, array $raw): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $mapped[$header] = trim((string) ($raw[$index] ?? ''));
        }

        return $mapped;
    }

    /**
     * @param  array<string, string>  $mapped
     * @param  array<string, int>  $seenKeys
     */
    private function hydrate(
        LegislationKind $kind,
        int $line,
        array $mapped,
        string $numberHeader,
        array &$seenKeys,
    ): LegislationImportCsvRow {
        $number = $mapped[$numberHeader] ?? '';
        $title = $mapped['title'] ?? '';
        $status = strtolower($mapped['status'] ?? '');
        $yearRaw = $mapped['series_year'] ?? '';
        $year = ctype_digit($yearRaw) ? (int) $yearRaw : 0;

        $error = $this->validate($kind, $number, $title, $status, $year, $mapped);

        $key = $error === null
            ? $this->normalizer->fromRegister($kind, $number, $year)
            : null;

        if ($error === null && $key === null) {
            $error = 'citation';
        }

        if ($key !== null && isset($seenKeys[$key->value()])) {
            $error = 'duplicate_in_csv';
        } elseif ($key !== null) {
            $seenKeys[$key->value()] = $line;
        }

        return new LegislationImportCsvRow(
            line: $line,
            number: $number,
            year: $year,
            title: $title,
            status: $status,
            fields: $this->normalizedFields($kind, $mapped, $number, $year, $title, $status),
            key: $error === null ? $key : null,
            error: $error,
        );
    }

    /**
     * @param  array<string, string>  $mapped
     */
    private function validate(
        LegislationKind $kind,
        string $number,
        string $title,
        string $status,
        int $year,
        array $mapped,
    ): ?string {
        if ($number === '' || $title === '' || $status === '' || $year < 1900 || $year > 2100) {
            return 'required';
        }

        if (! in_array($status, $kind->statuses(), true)) {
            return 'status';
        }

        foreach ($this->dateColumns($kind) as $column) {
            $value = $mapped[$column] ?? '';

            if ($value !== '' && ! $this->isDate($value)) {
                return 'date';
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $mapped
     * @return array<string, string|null>
     */
    private function normalizedFields(
        LegislationKind $kind,
        array $mapped,
        string $number,
        int $year,
        string $title,
        string $status,
    ): array {
        $fields = [
            $kind->numberColumn() => $number,
            'series_year' => (string) $year,
            'title' => $title,
            'status' => $status,
            'purpose' => $this->nullable($mapped['purpose'] ?? ''),
        ];

        foreach ($this->dateColumns($kind) as $column) {
            $fields[$column] = $this->nullable($mapped[$column] ?? '');
        }

        if ($kind === LegislationKind::Ordinance) {
            $fields['approving_authority'] = $this->nullable($mapped['approving_authority'] ?? '');
        }

        if ($kind === LegislationKind::Resolution) {
            $fields['category'] = $this->nullable($mapped['category'] ?? '');
            $fields['transmitted_to'] = $this->nullable($mapped['transmitted_to'] ?? '');
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function dateColumns(LegislationKind $kind): array
    {
        return match ($kind) {
            LegislationKind::Ordinance => [
                'enacted_on',
                'effectivity_date',
            ],
            LegislationKind::Resolution => [
                'adopted_on',
                'effectivity_date',
                'transmitted_on',
            ],
        };
    }

    private function isDate(string $value): bool
    {
        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string|null>  $raw
     */
    private function isEmptyRow(array $raw): bool
    {
        foreach ($raw as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string|null>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(
            static fn (?string $header): string => strtolower(trim(str_replace(' ', '_', (string) $header))),
            $headers,
        );
    }

    private function stripBom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }
}
