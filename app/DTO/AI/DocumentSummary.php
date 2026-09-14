<?php

namespace App\DTO\AI;

final readonly class DocumentSummary
{
    /**
     * @param  list<string>  $keyProvisions
     * @param  list<string>  $importantDates
     * @param  list<string>  $affectedOffices
     * @param  list<string>  $relatedDocsHints
     * @param  list<string>  $potentialIssues
     */
    public function __construct(
        public string $executiveSummary,
        public string $purpose,
        public array $keyProvisions,
        public array $importantDates,
        public ?string $financialInfo,
        public array $affectedOffices,
        public array $relatedDocsHints,
        public array $potentialIssues,
        public string $model,
        public ?string $generatedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMetadataJson(): array
    {
        return [
            'executive_summary' => $this->executiveSummary,
            'purpose' => $this->purpose,
            'key_provisions' => $this->keyProvisions,
            'important_dates' => $this->importantDates,
            'financial_info' => $this->financialInfo,
            'affected_offices' => $this->affectedOffices,
            'related_docs_hints' => $this->relatedDocsHints,
            'potential_issues' => $this->potentialIssues,
            'model' => $this->model,
            'generated_at' => $this->generatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromMetadataJson(array $data, string $model = 'stored'): self
    {
        return new self(
            executiveSummary: self::asString($data['executive_summary'] ?? ''),
            purpose: self::asString($data['purpose'] ?? ''),
            keyProvisions: self::asStringList($data['key_provisions'] ?? []),
            importantDates: self::asStringList($data['important_dates'] ?? []),
            financialInfo: self::asNullableString($data['financial_info'] ?? null),
            affectedOffices: self::asStringList($data['affected_offices'] ?? []),
            relatedDocsHints: self::asStringList($data['related_docs_hints'] ?? []),
            potentialIssues: self::asStringList($data['potential_issues'] ?? []),
            model: self::asString($data['model'] ?? $model),
            generatedAt: self::asNullableString($data['generated_at'] ?? null),
        );
    }

    /**
     * Models often return objects or arrays for string fields. Flatten them
     * so parsing never throws on mixed JSON shapes.
     */
    private static function asString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return self::flattenArray($value);
        }

        return '';
    }

    private static function asNullableString(mixed $value): ?string
    {
        $string = self::asString($value);

        return $string === '' ? null : $string;
    }

    /**
     * @return list<string>
     */
    private static function asStringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value)) {
            $string = self::asString($value);

            return $string === '' ? [] : [$string];
        }

        $items = [];

        foreach ($value as $item) {
            $string = self::asString($item);

            if ($string !== '') {
                $items[] = $string;
            }
        }

        return array_values($items);
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private static function flattenArray(array $value): string
    {
        $parts = [];

        foreach ($value as $key => $item) {
            $string = self::asString($item);

            if ($string === '') {
                continue;
            }

            $parts[] = is_string($key) ? "{$key}: {$string}" : $string;
        }

        return implode('; ', $parts);
    }
}
