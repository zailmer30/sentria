<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One secretariat change to a transcript segment. Original STT text stays on
 * the segment JSON; this row is the audit trail of what staff changed.
 *
 * @property string $transcript_id
 * @property int $segment_index
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $old_speaker_id
 * @property string|null $new_speaker_id
 * @property string|null $user_id
 * @property Carbon|null $created_at
 */
class TranscriptSegmentEdit extends Model
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
            'segment_index' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Transcript, $this> */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
