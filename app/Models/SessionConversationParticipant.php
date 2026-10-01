<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $conversation_id
 * @property string $user_id
 * @property Carbon $joined_at
 * @property Carbon|null $last_read_at
 * @property Carbon|null $left_at
 */
class SessionConversationParticipant extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_read_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SessionConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SessionConversation::class, 'conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrent(): bool
    {
        return $this->left_at === null;
    }
}
