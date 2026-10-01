<?php

namespace App\Models;

use App\Contracts\Legislation\HoldsSignedCopy;
use App\Models\Concerns\HasSignedCopy;
use Database\Factories\OrdinanceFactory;
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
 * @property Carbon|null $enacted_on
 * @property Carbon|null $effectivity_date
 * @property Carbon|null $imported_at
 */
#[UseFactory(OrdinanceFactory::class)]
class Ordinance extends Model implements Auditable, HoldsSignedCopy
{
    use AuditableTrait;

    /** @use HasFactory<OrdinanceFactory> */
    use HasFactory;

    use HasSignedCopy;
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
            'series_year' => 'integer',
            'enacted_on' => 'date',
            'effectivity_date' => 'date',
            'imported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Ordinance, $this> */
    public function amendedOrdinance(): BelongsTo
    {
        return $this->belongsTo(Ordinance::class, 'amends_ordinance_id');
    }

    /** @return HasMany<Ordinance, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(Ordinance::class, 'amends_ordinance_id');
    }

    /** @return BelongsTo<Ordinance, $this> */
    public function repealedBy(): BelongsTo
    {
        return $this->belongsTo(Ordinance::class, 'repealed_by_ordinance_id');
    }

    public function isInForce(): bool
    {
        $effectivity = $this->effectivity_date;

        return $this->repealed_by_ordinance_id === null
            && $this->status !== 'vetoed'
            && $effectivity instanceof Carbon
            && ! $effectivity->isFuture();
    }
}
