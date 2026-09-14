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
 * @property Carbon|null $approved_on
 * @property Carbon|null $vetoed_on
 * @property Carbon|null $veto_overridden_on
 * @property Carbon|null $effectivity_date
 * @property Carbon|null $publication_date
 * @property Carbon|null $sp_submitted_on
 * @property Carbon|null $sp_reviewed_on
 * @property string|null $sp_result
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
            'approved_on' => 'date',
            'vetoed_on' => 'date',
            'veto_overridden_on' => 'date',
            'effectivity_date' => 'date',
            'publication_date' => 'date',
            'sp_submitted_on' => 'date',
            'sp_reviewed_on' => 'date',
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
            && $this->vetoed_on === null
            && $effectivity instanceof Carbon
            && ! $effectivity->isFuture();
    }

    /**
     * Days after posting until a measure takes effect, from the filing
     * checklist. Falls back to the Local Government Code default of 10.
     */
    public static function daysUntilEffectivity(int|string|null $proposed): int
    {
        if (is_numeric($proposed) && (int) $proposed >= 1) {
            return (int) $proposed;
        }

        return 10;
    }
}
