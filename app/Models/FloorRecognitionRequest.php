<?php

namespace App\Models;

use Database\Factories\FloorRecognitionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[UseFactory(FloorRecognitionRequestFactory::class)]
/**
 * @property string $status
 * @property Carbon $raised_at
 * @property Carbon|null $resolved_at
 */
class FloorRecognitionRequest extends Model
{
    /** @use HasFactory<FloorRecognitionRequestFactory> */
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
            'raised_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LegislativeSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<AgendaItem, $this> */
    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /** @param  Builder<self>  $query */
    public function scopeOpenRecognized(Builder $query): Builder
    {
        return $query->where('status', 'recognized')->whereNull('resolved_at');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isOpenRecognized(): bool
    {
        return $this->status === 'recognized' && $this->resolved_at === null;
    }
}
