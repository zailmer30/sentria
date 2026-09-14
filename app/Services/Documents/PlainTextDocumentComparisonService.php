<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentComparisonService;
use App\DTO\Documents\ComparisonHunk;
use App\DTO\Documents\ComparisonResult;
use App\Models\DocumentVersion;

class PlainTextDocumentComparisonService implements DocumentComparisonService
{
    public function __construct(
        private readonly DocumentTextStore $textStore,
    ) {}

    public function compare(DocumentVersion $from, DocumentVersion $to): ComparisonResult
    {
        $linesA = $this->lines($this->textStore->get($from));
        $linesB = $this->lines($this->textStore->get($to));

        $hunks = $this->diff($linesA, $linesB);

        return new ComparisonResult($hunks);
    }

    /**
     * @return list<string>
     */
    private function lines(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        return preg_split('/\R/u', $text) ?: [];
    }

    /**
     * Simple line diff using longest common subsequence.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<ComparisonHunk>
     */
    private function diff(array $a, array $b): array
    {
        $lcs = $this->lcsTable($a, $b);
        $hunks = [];
        $i = count($a);
        $j = count($b);

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                $hunks[] = new ComparisonHunk('unchanged', [$a[$i - 1]]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                $hunks[] = new ComparisonHunk('added', [$b[$j - 1]]);
                $j--;
            } else {
                $hunks[] = new ComparisonHunk('removed', [$a[$i - 1]]);
                $i--;
            }
        }

        $hunks = array_reverse($hunks);

        return $this->mergeAdjacent($hunks);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return array<int, array<int, int>>
     */
    private function lcsTable(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);
        $table = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $table[$i][$j] = $table[$i - 1][$j - 1] + 1;
                } else {
                    $table[$i][$j] = max($table[$i - 1][$j], $table[$i][$j - 1]);
                }
            }
        }

        return $table;
    }

    /**
     * @param  list<ComparisonHunk>  $hunks
     * @return list<ComparisonHunk>
     */
    private function mergeAdjacent(array $hunks): array
    {
        if ($hunks === []) {
            return [];
        }

        /** @var list<ComparisonHunk> $merged */
        $merged = [];
        $current = $hunks[0];

        for ($index = 1; $index < count($hunks); $index++) {
            $next = $hunks[$index];

            if ($next->type === $current->type) {
                $current = new ComparisonHunk(
                    $current->type,
                    [...$current->lines, ...$next->lines],
                );
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;

        return $merged;
    }
}
