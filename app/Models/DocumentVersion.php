<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use Database\Factories\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Carbon|null $created_at
 * @property ProcessingStatus|null $processing_status
 * @property Carbon|null $processed_at
 */
#[UseFactory(DocumentVersionFactory::class)]
class DocumentVersion extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<int, string> */
    protected array $auditExclude = ['extracted_text_compressed'];

    /** @var list<string> */
    protected $hidden = ['extracted_text_compressed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'file_size' => 'integer',
            'page_count' => 'integer',
            'scanned_at' => 'datetime',
            'text_extracted_at' => 'datetime',
            'processing_status' => ProcessingStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<DocumentEmbedding, $this> */
    public function embeddings(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class);
    }

    /** @return HasMany<DocumentAnnotation, $this> */
    public function annotations(): HasMany
    {
        return $this->hasMany(DocumentAnnotation::class);
    }

    public function isSafeToServe(): bool
    {
        return in_array($this->scan_status, ['clean', 'skipped'], true);
    }
}
