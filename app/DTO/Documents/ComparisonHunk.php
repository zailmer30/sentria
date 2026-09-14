<?php

namespace App\DTO\Documents;

/**
 * @phpstan-type HunkType 'added'|'removed'|'unchanged'|'changed'
 */
readonly class ComparisonHunk
{
    /**
     * @param  HunkType  $type
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $type,
        public array $lines,
    ) {}
}
