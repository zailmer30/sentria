<?php

namespace App\DTO\AI;

final readonly class SearchResults
{
    /**
     * @param  list<SearchHit>  $hits
     */
    public function __construct(
        public string $query,
        public array $hits,
        public bool $ready = true,
    ) {}
}
