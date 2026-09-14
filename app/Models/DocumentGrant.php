<?php

namespace App\Models;

use Database\Factories\DocumentGrantFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
/**
 * An explicit grant of access to one document, for exactly one grantee:
 * a user, a role, or a committee.
 */
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Carbon|null $granted_at
 * @property Carbon|null $expires_at
 */
#[UseFactory(DocumentGrantFactory::class)]
class DocumentGrant extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<DocumentGrantFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Committee, $this> */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
