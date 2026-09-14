<?php

namespace App\Models;

use App\States\Publication\PublicationWorkflowStatus;
use App\States\Publication\Published;
use Database\Factories\PublicationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\ModelStates\HasStates;

/**
 * @property PublicationWorkflowStatus $status
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 * @property Carbon|null $unpublished_at
 */
#[UseFactory(PublicationFactory::class)]
class Publication extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<PublicationFactory> */
    use HasFactory;

    use HasStates;
    use HasUlids;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublicationWorkflowStatus::class,
            'categories' => 'array',
            'redaction_applied' => 'boolean',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'view_count' => 'integer',
            'download_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_slug';
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * The only scope the public portal may query. Anything outside it must
     * return 404, never 403.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', Published::$name)
            ->whereNotNull('published_at')
            ->whereNull('unpublished_at');
    }
}
