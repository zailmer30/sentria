<?php

namespace App\Models;

use App\Contracts\Legislation\HoldsSignedCopy;
use App\Models\Concerns\HasSignedCopy;
use Database\Factories\ResolutionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Carbon|null $adopted_on
 * @property Carbon|null $effectivity_date
 * @property Carbon|null $transmitted_on
 * @property Carbon|null $imported_at
 */
#[UseFactory(ResolutionFactory::class)]
class Resolution extends Model implements Auditable, HoldsSignedCopy
{
    use AuditableTrait;

    /** @use HasFactory<ResolutionFactory> */
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
            'adopted_on' => 'date',
            'effectivity_date' => 'date',
            'transmitted_on' => 'date',
            'imported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
