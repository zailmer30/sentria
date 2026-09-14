<?php

namespace App\Models;

use Database\Factories\MotionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Carbon $moved_at
 * @property Carbon|null $seconded_at
 * @property Carbon|null $disposed_at
 * @property string $status
 * @property string $text
 */
#[UseFactory(MotionFactory::class)]
class Motion extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<MotionFactory> */
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
            'moved_at' => 'datetime',
            'seconded_at' => 'datetime',
            'disposed_at' => 'datetime',
            'requires_vote' => 'boolean',
            'voting_round' => 'integer',
        ];
    }

    /** @return BelongsTo<LegislativeSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    /** @return BelongsTo<AgendaItem, $this> */
    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    /** @return BelongsTo<Motion, $this> */
    public function parentMotion(): BelongsTo
    {
        return $this->belongsTo(Motion::class, 'parent_motion_id');
    }

    /** @return HasMany<Motion, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(Motion::class, 'parent_motion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function seconder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seconded_by');
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
