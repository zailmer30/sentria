<?php

namespace App\Contracts\AI;

use App\DTO\AI\ConsistencyCheckResult;
use App\Models\Document;
use App\Models\User;

interface ConsistencyCheckService
{
    public function check(User $user, Document $document): ConsistencyCheckResult;
}
