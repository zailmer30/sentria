<?php

namespace App\Services\Legislation;

use App\DTO\Legislation\LegislationCitationKey;
use App\Enums\LegislationKind;

class LegislationCitationNormalizer
{
    public function fromRegister(LegislationKind $kind, string $number, int $year): ?LegislationCitationKey
    {
        if ($year < 1900 || $year > 2100) {
            return null;
        }

        $sequence = $this->sequenceFromNumber($number, $year);

        if ($sequence === null || $sequence < 1) {
            return null;
        }

        return new LegislationCitationKey($kind, $year, $sequence);
    }

    public function fromFilename(string $filename, LegislationKind $expectedKind): ?LegislationCitationKey
    {
        $base = pathinfo(str_replace('\\', '/', $filename), PATHINFO_FILENAME);
        $normalized = strtolower((string) preg_replace('/[._]+/', ' ', $base));
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));

        if ($normalized === '') {
            return null;
        }

        $detectedKind = $this->kindFromLabel($normalized);

        if ($detectedKind !== null && $detectedKind !== $expectedKind) {
            return null;
        }

        if (preg_match('/\b(?:ord|res)[\s-]*([12]\d{3})[\s-]+(\d+)\b/', $normalized, $match) === 1) {
            return $this->key($expectedKind, (int) $match[1], (int) $match[2]);
        }

        if (preg_match('/\b(?:no|number|#)?\s*(\d+)\s*(?:s|series)?\s*(?:of)?[\s-]*([12]\d{3})\b/', $normalized, $match) === 1) {
            return $this->key($expectedKind, (int) $match[2], (int) $match[1]);
        }

        if (preg_match('/\b(\d{1,4})[\s-]+([12]\d{3})\b/', $normalized, $match) === 1) {
            return $this->key($expectedKind, (int) $match[2], (int) $match[1]);
        }

        if (preg_match('/\b([12]\d{3})[\s-]+(\d{1,4})\b/', $normalized, $match) === 1) {
            return $this->key($expectedKind, (int) $match[1], (int) $match[2]);
        }

        return null;
    }

    private function sequenceFromNumber(string $number, int $year): ?int
    {
        if (preg_match_all('/\d+/', $number, $matches) === 0) {
            return null;
        }

        $parts = array_values(array_filter(
            array_map(intval(...), $matches[0]),
            static fn (int $value): bool => $value !== $year,
        ));

        if ($parts === []) {
            return null;
        }

        return $parts[array_key_last($parts)];
    }

    private function kindFromLabel(string $normalized): ?LegislationKind
    {
        if (preg_match('/\b(ordinance|ordinances)\b/', $normalized) === 1
            || preg_match('/\bord\b/', $normalized) === 1) {
            return LegislationKind::Ordinance;
        }

        if (preg_match('/\b(resolution|resolutions)\b/', $normalized) === 1
            || preg_match('/\bres\b/', $normalized) === 1) {
            return LegislationKind::Resolution;
        }

        return null;
    }

    private function key(LegislationKind $kind, int $year, int $sequence): ?LegislationCitationKey
    {
        if ($year < 1900 || $year > 2100 || $sequence < 1) {
            return null;
        }

        return new LegislationCitationKey($kind, $year, $sequence);
    }
}
