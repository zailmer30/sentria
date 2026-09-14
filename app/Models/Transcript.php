<?php

namespace App\Models;

use Database\Factories\TranscriptFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A machine or human transcript of floor proceedings. Transcripts are a
 * reference aid; official vote results always come from the `votes` table.
 */
#[UseFactory(TranscriptFactory::class)]
class Transcript extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<TranscriptFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<int, string> */
    protected array $auditExclude = ['full_text', 'segments'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'average_confidence' => 'float',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'channel_map_snapshot' => 'array',
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isMachineGenerated(): bool
    {
        return in_array($this->source, ['live_stt', 'chamber_channels'], true);
    }
}
