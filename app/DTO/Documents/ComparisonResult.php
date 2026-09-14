<?php

namespace App\DTO\Documents;

readonly class ComparisonResult
{
    /**
     * @param  list<ComparisonHunk>  $hunks
     */
    public function __construct(
        public array $hunks,
    ) {}

    /**
     * @return array{hunks: list<array{type: string, lines: list<string>}>}
     */
    public function toArray(): array
    {
        return [
            'hunks' => array_map(
                static fn (ComparisonHunk $hunk): array => [
                    'type' => $hunk->type,
                    'lines' => $hunk->lines,
                ],
                $this->hunks,
            ),
        ];
    }
}
