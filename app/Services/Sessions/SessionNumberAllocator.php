<?php

namespace App\Services\Sessions;

use App\Enums\SessionType;
use App\Models\LegislativeSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class SessionNumberAllocator
{
    /**
     * @return array{session_number: string, title: string, sequence: int}
     */
    public function preview(SessionType $type, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        $sequence = $this->latestSequence($type, $year) + 1;

        return $this->identity($type, $year, $sequence);
    }

    /**
     * @return array<string, array{session_number: string, title: string, sequence: int}>
     */
    public function previewAll(?int $year = null): array
    {
        $year ??= (int) now()->year;
        $latest = $this->latestSequences($year);
        $previews = [];

        foreach (SessionType::cases() as $type) {
            $previews[$type->value] = $this->identity($type, $year, ($latest[$type->tag()] ?? 0) + 1);
        }

        return $previews;
    }

    /**
     * @return array{session_number: string, title: string, sequence: int}
     */
    public function allocate(SessionType $type, ?int $year = null): array
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($type, $year): array {
            $this->lockSeries($type, $year);

            return $this->identity($type, $year, $this->latestSequence($type, $year) + 1);
        });
    }

    /**
     * @return array{session_number: string, title: string, sequence: int}
     */
    private function identity(SessionType $type, int $year, int $sequence): array
    {
        return [
            'session_number' => sprintf('%s-%d-%05d', $type->tag(), $year, $sequence),
            'title' => $this->title($type, $sequence),
            'sequence' => $sequence,
        ];
    }

    private function lockSeries(SessionType $type, int $year): void
    {
        DB::statement('select pg_advisory_xact_lock(hashtext(?))', [
            "session-number:{$type->tag()}:{$year}",
        ]);
    }

    private function latestSequence(SessionType $type, int $year): int
    {
        $prefix = $this->prefix($type, $year);
        $latest = 0;

        foreach ($this->numbersWithPrefix($prefix) as $number) {
            $latest = max($latest, $this->sequenceFrom($prefix, $number));
        }

        return $latest;
    }

    /**
     * @return array<string, int>
     */
    private function latestSequences(int $year): array
    {
        $prefixes = [];

        foreach (SessionType::cases() as $type) {
            $prefixes[$type->tag()] = $this->prefix($type, $year);
        }

        $latest = [];

        foreach ($this->numbersWithPrefixes(array_values($prefixes)) as $number) {
            foreach ($prefixes as $tag => $prefix) {
                if (str_starts_with($number, $prefix)) {
                    $latest[$tag] = max($latest[$tag] ?? 0, $this->sequenceFrom($prefix, $number));
                    break;
                }
            }
        }

        return $latest;
    }

    /**
     * @return list<string>
     */
    private function numbersWithPrefix(string $prefix): array
    {
        $numbers = [];

        foreach (
            LegislativeSession::query()
                ->withTrashed()
                ->where('session_number', 'like', $prefix.'%')
                ->pluck('session_number') as $number
        ) {
            if (is_string($number) && $number !== '') {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    /**
     * @param  list<string>  $prefixes
     * @return list<string>
     */
    private function numbersWithPrefixes(array $prefixes): array
    {
        $numbers = [];

        foreach (
            LegislativeSession::query()
                ->withTrashed()
                ->where(function ($query) use ($prefixes): void {
                    foreach ($prefixes as $prefix) {
                        $query->orWhere('session_number', 'like', $prefix.'%');
                    }
                })
                ->pluck('session_number') as $number
        ) {
            if (is_string($number) && $number !== '') {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    private function prefix(SessionType $type, int $year): string
    {
        return sprintf('%s-%d-', $type->tag(), $year);
    }

    private function title(SessionType $type, int $sequence): string
    {
        $ordinal = Number::ordinal($sequence, 'en');

        return (is_string($ordinal) ? $ordinal : (string) $sequence).' '.$type->label();
    }

    private function sequenceFrom(string $prefix, string $number): int
    {
        if (! str_starts_with($number, $prefix)) {
            return 0;
        }

        $suffix = substr($number, strlen($prefix));

        return ctype_digit($suffix) ? (int) $suffix : 0;
    }
}
