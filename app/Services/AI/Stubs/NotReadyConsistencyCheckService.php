<?php

namespace App\Services\AI\Stubs;

use App\Contracts\AI\ConsistencyCheckService;
use App\DTO\AI\ConsistencyCheckResult;
use App\Models\Document;
use App\Models\User;

class NotReadyConsistencyCheckService implements ConsistencyCheckService
{
    use ThrowsNotReady;

    public function check(User $user, Document $document): ConsistencyCheckResult
    {
        $this->notReady('3c');
    }
}
