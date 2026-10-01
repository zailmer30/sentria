<?php

namespace App\Models;

use Database\Factories\SessionMessageFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plain-text floor chat line. Bodies are never audited and are purged after the sitting.
 *
 * @property string $conversation_id
 * @property string $user_id
 * @property string $body
 */
#[UseFactory(SessionMessageFactory::class)]
class SessionMessage extends Model
{
    /** @use HasFactory<SessionMessageFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<SessionConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SessionConversation::class, 'conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
