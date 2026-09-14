<?php

namespace App\Models;

use Database\Factories\ChamberChannelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Fixed mapping from a multi-channel audio interface index to a seated member
 * (or a gallery / resource channel when user_id is null).
 */
#[UseFactory(ChamberChannelFactory::class)]
class ChamberChannel extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<ChamberChannelFactory> */
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
            'channel_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('channel_index');
    }

    public function displayLabel(): string
    {
        if (filled($this->label)) {
            return (string) $this->label;
        }

        $name = $this->member?->display_name;

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return 'Channel '.$this->channel_index;
    }
}
