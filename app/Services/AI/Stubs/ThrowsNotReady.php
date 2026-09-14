<?php

namespace App\Services\AI\Stubs;

use LogicException;

trait ThrowsNotReady
{
    protected function notReady(string $slice = '3b/3c'): never
    {
        throw new LogicException("Not ready — slice {$slice}");
    }
}
