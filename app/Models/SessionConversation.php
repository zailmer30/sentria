<?php

namespace App\Models;

use App\Enums\SessionConversationType;
use Database\Factories\SessionConversationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ephemeral floor thread for one sitting. Not an official record.
 *
 * @property string $session_id
 * @property SessionConversationType $type
 * @property string|null $name
 * @property string|null $direct_pair_key
 * @property string $created_by
 * @property Carbon|null $last_message_at
 */
#[UseFactory(SessionConversationFactory::class)]
class SessionConversation extends Model
{
    /** @use HasFactory<SessionConversationFactory> */
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
            'type' => SessionConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }

    public static function directPairKey(string $firstUserId, string $secondUserId): string
    {
        return $firstUserId < $secondUserId
            ? "{$firstUserId}:{$secondUserId}"
            : "{$secondUserId}:{$firstUserId}";
    }

    /** @return BelongsTo<LegislativeSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SessionConversationParticipant, $this> */
    public function participantRows(): HasMany
    {
        return $this->hasMany(SessionConversationParticipant::class, 'conversation_id');
    }

    /** @return HasMany<SessionConversationParticipant, $this> */
    public function currentParticipantRows(): HasMany
    {
        return $this->participantRows()->whereNull('left_at');
    }

    /** @return HasMany<SessionMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SessionMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function isGroup(): bool
    {
        return $this->type === SessionConversationType::Group;
    }

    public function isDirect(): bool
    {
        return $this->type === SessionConversationType::Direct;
    }

    public function isCurrentParticipant(User $user): bool
    {
        return $this->currentParticipantRows()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForParticipant(Builder $query, User $user): Builder
    {
        return $query->whereHas('currentParticipantRows', function (Builder $participants) use ($user): void {
            $participants->where('user_id', $user->getKey());
        });
    }
}
