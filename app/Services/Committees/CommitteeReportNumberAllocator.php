<?php

namespace App\Services\Committees;

use App\Models\CommitteeReport;
use Illuminate\Support\Facades\DB;

class CommitteeReportNumberAllocator
{
    public function preview(?int $year = null): string
    {
        $year ??= (int) now()->year;

        return $this->format($year, $this->latestSequence($year) + 1);
    }

    public function allocate(?int $year = null): string
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($year): string {
            $this->lockSeries($year);

            return $this->format($year, $this->latestSequence($year) + 1);
        });
    }

    private function lockSeries(int $year): void
    {
        DB::statement('select pg_advisory_xact_lock(hashtext(?))', [
            "committee-report:{$year}",
        ]);
    }

    private function latestSequence(int $year): int
    {
        $prefix = $this->prefix($year);
        $latest = 0;

        foreach ($this->numbersWithPrefix($prefix) as $number) {
            $latest = max($latest, $this->sequenceFrom($prefix, $number));
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
            CommitteeReport::query()
                ->withTrashed()
                ->where('report_number', 'like', $prefix.'%')
                ->pluck('report_number') as $number
        ) {
            if (is_string($number) && $number !== '') {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    private function prefix(int $year): string
    {
        return sprintf('CR-%d-', $year);
    }

    private function format(int $year, int $sequence): string
    {
        return sprintf('CR-%d-%03d', $year, $sequence);
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
