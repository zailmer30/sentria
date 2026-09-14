<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DocumentReferenceAllocator
{
    public function preview(DocumentType $type, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        return $this->format($type, $year, $this->latestSequence($type, $year) + 1);
    }

    /**
     * @return array<string, string>
     */
    public function previewAll(?int $year = null): array
    {
        $year ??= (int) now()->year;
        $latest = $this->latestSequences($year);

        $previews = [];

        foreach (DocumentType::cases() as $type) {
            $previews[$type->value] = $this->format($type, $year, ($latest[$type->tag()] ?? 0) + 1);
        }

        return $previews;
    }

    public function allocate(DocumentType $type, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($type, $year): string {
            $this->lockSeries($type, $year);

            return $this->format($type, $year, $this->latestSequence($type, $year) + 1);
        });
    }

    private function lockSeries(DocumentType $type, int $year): void
    {
        DB::statement('select pg_advisory_xact_lock(hashtext(?))', [
            "document-ref:{$type->tag()}:{$year}",
        ]);
    }

    private function latestSequence(DocumentType $type, int $year): int
    {
        $prefix = $this->prefix($type, $year);
        $latest = 0;

        foreach ($this->referencesWithPrefix($prefix) as $reference) {
            $latest = max($latest, $this->sequenceFrom($prefix, $reference));
        }

        return $latest;
    }

    /**
     * @return array<string, int>
     */
    private function latestSequences(int $year): array
    {
        $prefixes = [];

        foreach (DocumentType::cases() as $type) {
            $prefixes[$type->tag()] = $this->prefix($type, $year);
        }

        $latest = [];

        foreach ($this->referencesWithPrefixes(array_values($prefixes)) as $reference) {
            foreach ($prefixes as $tag => $prefix) {
                if (str_starts_with($reference, $prefix)) {
                    $latest[$tag] = max($latest[$tag] ?? 0, $this->sequenceFrom($prefix, $reference));
                    break;
                }
            }
        }

        return $latest;
    }

    /**
     * @return list<string>
     */
    private function referencesWithPrefix(string $prefix): array
    {
        $references = [];

        foreach (
            Document::query()
                ->withTrashed()
                ->where('reference_number', 'like', $prefix.'%')
                ->pluck('reference_number') as $reference
        ) {
            if (is_string($reference) && $reference !== '') {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @param  list<string>  $prefixes
     * @return list<string>
     */
    private function referencesWithPrefixes(array $prefixes): array
    {
        $references = [];

        foreach (
            Document::query()
                ->withTrashed()
                ->where(function ($query) use ($prefixes): void {
                    foreach ($prefixes as $prefix) {
                        $query->orWhere('reference_number', 'like', $prefix.'%');
                    }
                })
                ->pluck('reference_number') as $reference
        ) {
            if (is_string($reference) && $reference !== '') {
                $references[] = $reference;
            }
        }

        return $references;
    }

    private function prefix(DocumentType $type, int $year): string
    {
        return sprintf('%s-%d-', $type->tag(), $year);
    }

    private function format(DocumentType $type, int $year, int $sequence): string
    {
        return sprintf('%s-%d-%05d', $type->tag(), $year, $sequence);
    }

    private function sequenceFrom(string $prefix, string $reference): int
    {
        if (! str_starts_with($reference, $prefix)) {
            return 0;
        }

        $suffix = substr($reference, strlen($prefix));

        return ctype_digit($suffix) ? (int) $suffix : 0;
    }
}
