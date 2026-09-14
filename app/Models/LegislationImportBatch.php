<?php

namespace App\Models;

use App\Enums\LegislationKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property LegislationKind $kind
 * @property string $status
 * @property Carbon|null $committed_at
 */
class LegislationImportBatch extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LegislationKind::class,
            'preview' => 'array',
            'result' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPreviewed(): bool
    {
        return $this->status === 'previewed';
    }

    public function isCommitted(): bool
    {
        return $this->status === 'committed';
    }
}
