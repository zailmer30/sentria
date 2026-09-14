<?php

namespace App\Contracts\AI;

use App\DTO\AI\SearchResults;
use App\Models\User;

interface LegislativeSearchService
{
    public function search(User $user, string $query, int $limit = 8): SearchResults;
}
