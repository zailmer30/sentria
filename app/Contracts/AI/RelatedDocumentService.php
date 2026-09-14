<?php

namespace App\Contracts\AI;

use App\DTO\AI\RelatedDocumentsResult;
use App\Models\Document;
use App\Models\User;

interface RelatedDocumentService
{
    public function findRelated(User $user, Document $document, int $limit = 5): RelatedDocumentsResult;
}
