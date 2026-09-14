<?php

namespace App\Models;

use App\Enums\ChamberFeed;
use App\States\Session\SessionStatus;
use Database\Factories\LegislativeSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\ModelStates\HasStates;

/**
 * A legislative session. Named `LegislativeSession` to keep it distinct from
 * HTTP sessions, which live in the `http_sessions` table.
 *
 * @property string $session_number
 * @property string $title
 * @property string $type
 * @property SessionStatus $status
 * @property int|null $legislative_year
 * @property Carbon|null $scheduled_start_at
 * @property Carbon|null $scheduled_end_at
 * @property Carbon|null $actual_start_at
 * @property Carbon|null $actual_end_at
 * @property Carbon|null $agenda_locked_at
 * @property Carbon|null $documents_distributed_at
 * @property Carbon|null $adjourned_at
 * @property Carbon|null $recess_ends_at
 * @property Carbon|null $quorum_declared_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $venue
 * @property string|null $presiding_officer_id
 * @property string|null $secretary_id
 * @property int|null $seated_member_count
 * @property int|null $quorum_required
 * @property bool $is_public
 * @property bool $recording_enabled
 * @property ChamberFeed $capture_mode
 * @property string|null $notes
 * @property string|null $secretariat_minutes
 * @property string $hall_display_stage
 * @property string|null $hall_display_agenda_item_id
 * @property array<string, mixed>|null $hall_display_view
 */
#[Table('sessions')]
#[UseFactory(LegislativeSessionFactory::class)]
class LegislativeSession extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<LegislativeSessionFactory> */
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
            'status' => SessionStatus::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'agenda_locked_at' => 'datetime',
            'documents_distributed_at' => 'datetime',
            'adjourned_at' => 'datetime',
            'recess_ends_at' => 'datetime',
            'quorum_declared_at' => 'datetime',
            'is_public' => 'boolean',
            'seated_member_count' => 'integer',
            'quorum_required' => 'integer',
            'legislative_year' => 'integer',
            'hall_display_view' => 'array',
            'recording_enabled' => 'boolean',
            'capture_mode' => ChamberFeed::class,
        ];
    }

    /**
     * Whole seconds left on a timed recess. Null when the sitting is not in one.
     * The floor clocks from this snapshot so a browser clock that disagrees
     * with the server still opens on 10:00, not 10:31.
     */
    public function recessRemainingSeconds(): ?int
    {
        if ($this->recess_ends_at === null) {
            return null;
        }

        return max(0, $this->recess_ends_at->getTimestamp() - now()->getTimestamp());
    }

    public function chamberFeed(): ChamberFeed
    {
        return $this->capture_mode instanceof ChamberFeed
            ? $this->capture_mode
            : ChamberFeed::fromMixed($this->capture_mode);
    }

    /** @return BelongsTo<User, $this> */
    public function presidingOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presiding_officer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    /** @return HasMany<AgendaItem, $this> */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(AgendaItem::class, 'session_id')->orderBy('position');
    }

    /** @return HasMany<SessionAttendance, $this> */
    public function attendance(): HasMany
    {
        return $this->hasMany(SessionAttendance::class, 'session_id');
    }

    /** @return HasMany<Motion, $this> */
    public function motions(): HasMany
    {
        return $this->hasMany(Motion::class, 'session_id');
    }

    /** @return HasMany<FloorRecognitionRequest, $this> */
    public function floorRecognitionRequests(): HasMany
    {
        return $this->hasMany(FloorRecognitionRequest::class, 'session_id');
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'session_id');
    }

    /** @return HasOne<Minutes, $this> */
    public function minutes(): HasOne
    {
        return $this->hasOne(Minutes::class, 'session_id');
    }

    /** @return HasMany<Transcript, $this> */
    public function transcripts(): HasMany
    {
        return $this->hasMany(Transcript::class, 'session_id');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'session_id');
    }

    /** @return BelongsTo<AgendaItem, $this> */
    public function hallDisplayAgendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'hall_display_agenda_item_id');
    }

    /**
     * What the hall board should show. Reads raw attributes so a missing
     * migration cannot trip Model::shouldBeStrict() on the floor pages.
     *
     * @return array{
     *     stage: string,
     *     agenda_item_id: string|null,
     *     view: array{zoom: float, page: int, relative_x: float, relative_y: float}|null
     * }
     */
    public function hallDisplayState(): array
    {
        $attrs = $this->getAttributes();
        $rawStage = $attrs['hall_display_stage'] ?? 'item';
        $stage = in_array($rawStage, ['document', 'results'], true) ? $rawStage : 'item';

        $view = null;

        if ($stage === 'document' && array_key_exists('hall_display_view', $attrs) && $attrs['hall_display_view'] !== null) {
            $raw = $attrs['hall_display_view'];

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }

            if (is_array($raw)) {
                $view = [
                    'zoom' => max(0.1, min(10.0, (float) ($raw['zoom'] ?? 1.0))),
                    'page' => max(1, (int) ($raw['page'] ?? 1)),
                    'relative_x' => max(0.0, min(1.0, (float) ($raw['relative_x'] ?? 0.0))),
                    'relative_y' => max(0.0, min(1.0, (float) ($raw['relative_y'] ?? 0.0))),
                ];
            }
        }

        return [
            'stage' => $stage,
            'agenda_item_id' => in_array($stage, ['document', 'results'], true)
                ? ($attrs['hall_display_agenda_item_id'] ?? null)
                : null,
            'view' => $view,
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_start_at', '>=', now())->orderBy('scheduled_start_at');
    }
}
