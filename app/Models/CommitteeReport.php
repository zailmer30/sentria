<?php

namespace App\Models;

use Database\Factories\CommitteeReportFactory;
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
 * @property Carbon|null $submitted_at
 */
#[UseFactory(CommitteeReportFactory::class)]
class CommitteeReport extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<CommitteeReportFactory> */
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
            'signatories' => 'array',
            'submitted_at' => 'datetime',
            'adopted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CommitteeReferral, $this> */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(CommitteeReferral::class, 'committee_referral_id');
    }

    /** @return BelongsTo<Committee, $this> */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function subjectDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'subject_document_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
