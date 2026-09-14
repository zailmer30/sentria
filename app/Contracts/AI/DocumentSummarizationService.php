<?php

namespace App\Contracts\AI;

use App\DTO\AI\DocumentSummary;
use App\Models\Document;
use App\Models\User;

interface DocumentSummarizationService
{
    public function summarize(User $user, Document $document, bool $refresh = false): DocumentSummary;

    public function loadStoredSummary(Document $document): ?DocumentSummary;
}
