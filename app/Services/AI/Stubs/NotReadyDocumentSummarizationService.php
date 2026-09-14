<?php

namespace App\Services\AI\Stubs;

use App\Contracts\AI\DocumentSummarizationService;
use App\DTO\AI\DocumentSummary;
use App\Models\Document;
use App\Models\User;

class NotReadyDocumentSummarizationService implements DocumentSummarizationService
{
    use ThrowsNotReady;

    public function summarize(User $user, Document $document, bool $refresh = false): DocumentSummary
    {
        $this->notReady('3b');
    }

    public function loadStoredSummary(Document $document): ?DocumentSummary
    {
        return null;
    }
}
