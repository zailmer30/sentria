<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $signed_copy_disk
 * @property string|null $signed_copy_path
 * @property string|null $signed_copy_filename
 * @property string|null $signed_copy_mime
 * @property int|null $signed_copy_size
 * @property string|null $signed_copy_checksum
 * @property string|null $signed_copy_scan_status
 * @property Carbon|null $signed_copy_uploaded_at
 * @property string|null $signed_copy_uploaded_by
 */
trait HasSignedCopy
{
    public function initializeHasSignedCopy(): void
    {
        $this->mergeCasts([
            'signed_copy_size' => 'integer',
            'signed_copy_uploaded_at' => 'datetime',
        ]);

        $this->hidden = array_values(array_unique([
            ...$this->hidden,
            'signed_copy_disk',
            'signed_copy_path',
            'signed_copy_checksum',
        ]));
    }

    /** @return BelongsTo<User, $this> */
    public function signedCopyUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_copy_uploaded_by');
    }

    public function signedCopyIsAttached(): bool
    {
        return is_string($this->signed_copy_path) && $this->signed_copy_path !== '';
    }

    public function hasSignedCopy(): bool
    {
        return $this->signedCopyIsAttached() && $this->signedCopyIsSafeToServe();
    }

    public function signedCopyIsSafeToServe(): bool
    {
        return in_array($this->signed_copy_scan_status, ['clean', 'skipped'], true);
    }
}
