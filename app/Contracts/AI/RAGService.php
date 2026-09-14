<?php

namespace App\Contracts\AI;

use App\DTO\AI\RagResult;
use App\Models\Document;
use App\Models\User;

interface RAGService
{
    public function ask(
        User $user,
        string $question,
        ?string $conversationId = null,
        ?Document $documentContext = null,
    ): RagResult;
}
