<?php

namespace App\Models;

use App\States\Minutes\AiDraft;
use App\States\Minutes\MinutesStatus;
use App\States\Minutes\SessionCompleted;
use Database\Factories\MinutesFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
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
 * Minutes of a session. The AI draft is stored separately from `content` so a
 * regeneration can never overwrite the human-edited official record, and AI
 * never advances the workflow to Final Minutes on its own.
 *
 * @property MinutesStatus $status
 * @property array<string, mixed>|null $ai_metadata
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $finalized_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $updated_at
 */
#[Table('minutes')]
#[UseFactory(MinutesFactory::class)]
class Minutes extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<MinutesFactory> */
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
            'status' => MinutesStatus::class,
            'revision' => 'integer',
            'ai_generated_at' => 'datetime',
            'ai_metadata' => 'array',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LegislativeSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function hasUnverifiedAiContent(): bool
    {
        return $this->ai_draft !== null && $this->approved_at === null;
    }

    /**
     * First generation is allowed from session-completed. Regeneration is
     * allowed only while the record is still an untouched AI draft.
     */
    public function allowsDraftGeneration(): bool
    {
        if ($this->status instanceof SessionCompleted) {
            return true;
        }

        if (! $this->status instanceof AiDraft) {
            return false;
        }

        return (string) $this->content === (string) $this->ai_draft;
    }
}
