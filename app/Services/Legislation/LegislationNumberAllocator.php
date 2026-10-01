<?php

namespace App\Services\Legislation;

use App\Enums\LegislationKind;
use App\Models\Ordinance;
use App\Models\Resolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ordinance numbers are one continuing sequence (012, 013, …) from the first
 * ordinance onward. The series year is stored separately and does not restart
 * the count. Resolutions stay unique per series year (RES-2026-012).
 */
class LegislationNumberAllocator
{
    public function preview(LegislationKind $kind, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        return $this->format($kind, $year, $this->latestSequence($kind, $year) + 1);
    }

    /**
     * Must be called inside the transaction that inserts the row, so the
     * advisory lock is held until the new number is committed.
     */
    public function allocate(LegislationKind $kind, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($kind, $year): string {
            DB::statement('select pg_advisory_xact_lock(hashtext(?))', [
                $this->lockKey($kind, $year),
            ]);

            return $this->format($kind, $year, $this->latestSequence($kind, $year) + 1);
        });
    }

    private function latestSequence(LegislationKind $kind, int $year): int
    {
        if ($kind === LegislationKind::Ordinance) {
            return $this->latestOrdinanceSequence();
        }

        $prefix = $this->prefix($kind, $year);
        $column = $kind->numberColumn();
        $latest = 0;

        foreach ($this->query($kind)->where($column, 'like', $prefix.'%')->pluck($column) as $number) {
            if (! is_string($number)) {
                continue;
            }

            $suffix = substr($number, strlen($prefix));

            if (ctype_digit($suffix)) {
                $latest = max($latest, (int) $suffix);
            }
        }

        return $latest;
    }

    /**
     * The highest ordinance number on the register, in any series year.
     * Plain numbers (12, 012) and older coded numbers (ORD-2026-007) both count.
     */
    private function latestOrdinanceSequence(): int
    {
        $latest = 0;
        $column = LegislationKind::Ordinance->numberColumn();

        foreach ($this->query(LegislationKind::Ordinance)->pluck($column) as $number) {
            if (! is_string($number)) {
                continue;
            }

            $sequence = $this->ordinanceSequence($number);

            if ($sequence !== null) {
                $latest = max($latest, $sequence);
            }
        }

        return $latest;
    }

    private function ordinanceSequence(string $number): ?int
    {
        $number = trim($number);

        if (preg_match('/^\d+$/', $number) === 1) {
            return (int) $number;
        }

        if (preg_match('/(\d+)\s*$/', $number, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @return Builder<Ordinance>|Builder<Resolution>
     */
    private function query(LegislationKind $kind): Builder
    {
        return match ($kind) {
            LegislationKind::Ordinance => Ordinance::query()->withTrashed(),
            LegislationKind::Resolution => Resolution::query()->withTrashed(),
        };
    }

    private function prefix(LegislationKind $kind, int $year): string
    {
        return sprintf('%s-%d-', $this->code($kind), $year);
    }

    private function format(LegislationKind $kind, int $year, int $sequence): string
    {
        if ($kind === LegislationKind::Ordinance) {
            return sprintf('%03d', $sequence);
        }

        return sprintf('%s-%d-%03d', $this->code($kind), $year, $sequence);
    }

    private function lockKey(LegislationKind $kind, int $year): string
    {
        if ($kind === LegislationKind::Ordinance) {
            return "legislation:{$kind->value}";
        }

        return "legislation:{$kind->value}:{$year}";
    }

    private function code(LegislationKind $kind): string
    {
        return match ($kind) {
            LegislationKind::Ordinance => 'ORD',
            LegislationKind::Resolution => 'RES',
        };
    }
}
