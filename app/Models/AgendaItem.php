<?php

namespace App\Models;

use Database\Factories\AgendaItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string|null $parent_id
 * @property int $position
 * @property string|null $item_number
 * @property string $title
 * @property string|null $description
 * @property string $category
 * @property string $status
 * @property string|null $document_id
 * @property bool $requires_vote
 * @property int $voting_round
 * @property list<int>|null $silent_voting_rounds
 * @property Carbon|null $voting_open_at
 * @property Carbon|null $voting_opened_at
 * @property Carbon|null $voting_closed_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $postponed_at
 * @property string|null $postponed_from_category
 * @property string|null $postponed_from_parent_id
 * @property string|null $carried_to_session_id
 * @property string|null $carried_to_agenda_item_id
 */
#[UseFactory(AgendaItemFactory::class)]
class AgendaItem extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<AgendaItemFactory> */
    use HasFactory;

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
            'position' => 'integer',
            'time_allotment_minutes' => 'integer',
            'reading_number' => 'integer',
            'requires_vote' => 'boolean',
            'voting_round' => 'integer',
            'silent_voting_rounds' => 'array',
            'voting_open_at' => 'datetime',
            'voting_opened_at' => 'datetime',
            'voting_closed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'postponed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LegislativeSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    /** @return BelongsTo<AgendaItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'parent_id');
    }

    /** @return HasMany<AgendaItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(AgendaItem::class, 'parent_id')->orderBy('position');
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

    /** @return BelongsTo<User, $this> */
    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presented_by');
    }

    /** @return HasMany<Motion, $this> */
    public function motions(): HasMany
    {
        return $this->hasMany(Motion::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Whether this voting round was opened as a silent (masked) ballot.
     * Identities stay on the record; the hall and member floors hide them.
     */
    public function isSilentVotingRound(int $round): bool
    {
        if ($round < 1) {
            return false;
        }

        $rounds = array_map('intval', $this->silent_voting_rounds ?? []);

        return in_array($round, $rounds, true);
    }

    /** @return BelongsTo<LegislativeSession, $this> */
    public function carriedToSession(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'carried_to_session_id');
    }

    /** @return BelongsTo<AgendaItem, $this> */
    public function carriedToAgendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'carried_to_agenda_item_id');
    }
}
