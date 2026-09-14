<?php

namespace App\Services\AI\Stubs;

use App\Contracts\AI\RelatedDocumentService;
use App\DTO\AI\RelatedDocumentsResult;
use App\Models\Document;
use App\Models\User;

class NotReadyRelatedDocumentService implements RelatedDocumentService
{
    use ThrowsNotReady;

    public function findRelated(User $user, Document $document, int $limit = 5): RelatedDocumentsResult
    {
        $this->notReady('3b');
    }
}
