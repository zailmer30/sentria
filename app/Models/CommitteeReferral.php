<?php

namespace App\Models;

use Database\Factories\CommitteeReferralFactory;
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
 * @property Carbon|null $referred_at
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property string|null $outcome_notes
 */
#[UseFactory(CommitteeReferralFactory::class)]
class CommitteeReferral extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<CommitteeReferralFactory> */
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
            'is_primary' => 'boolean',
            'referred_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Committee, $this> */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
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

    /** @return BelongsTo<User, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /** @return HasMany<CommitteeReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(CommitteeReport::class);
    }

    public function isOverdue(): bool
    {
        $dueAt = $this->due_at;

        return $dueAt instanceof Carbon
            && $this->completed_at === null
            && $dueAt->isPast();
    }

    /**
     * Next referral statuses from this one. Keep in lockstep with the committee desk.
     *
     * @return list<string>
     */
    public function successors(): array
    {
        return match ($this->status) {
            'pending' => ['in-review'],
            'in-review' => ['reported', 'returned', 'closed'],
            default => [],
        };
    }
}
