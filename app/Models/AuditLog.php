<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Append-only legislative audit trail. Each row stores the SHA-256 hash of its
 * own canonical content plus the previous row's hash, so removing or editing a
 * row breaks the chain. Writes go through App\Services\Audit\AuditLogger;
 * `php artisan audit:verify-chain` checks integrity.
 *
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property string|null $actor_label
 * @property string|null $actor_role
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $route
 * @property string|null $method
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property array<string, mixed>|null $context
 * @property string|null $message
 * @property string|null $previous_hash
 * @property string $hash
 * @property Carbon|null $occurred_at
 * @property int|null $occurred_at_epoch
 */
class AuditLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
            'is_ai_actor' => 'boolean',
            'occurred_at' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Audit log entries are append-only and cannot be modified.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Audit log entries are append-only and cannot be deleted.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
