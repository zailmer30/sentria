<?php

namespace App\Models;

use Database\Factories\AiConversationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(AiConversationFactory::class)]
class AiConversation extends Model
{
    /** @use HasFactory<AiConversationFactory> */
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
            'message_count' => 'integer',
            'last_message_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('created_at');
    }
}
