<?php

namespace App\Contracts\Legislation;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ordinance or resolution that can carry the wet-signed final PDF
 * released to the public after publication.
 */
interface HoldsSignedCopy
{
    public function hasSignedCopy(): bool;

    public function signedCopyIsSafeToServe(): bool;

    /** @return BelongsTo<User, $this> */
    public function signedCopyUploader(): BelongsTo;
}
