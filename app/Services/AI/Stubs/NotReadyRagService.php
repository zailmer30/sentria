<?php

namespace App\Services\AI\Stubs;

use App\Contracts\AI\RAGService;
use App\DTO\AI\RagResult;
use App\Models\Document;
use App\Models\User;

class NotReadyRagService implements RAGService
{
    use ThrowsNotReady;

    public function ask(
        User $user,
        string $question,
        ?string $conversationId = null,
        ?Document $documentContext = null,
    ): RagResult {
        $this->notReady('3b');
    }
}
