<?php

namespace App\Models;

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\States\Document\DocumentWorkflowStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastsAttribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\ModelStates\HasStates;

/**
 * @property DocumentType $document_type
 * @property DocumentWorkflowStatus $status
 * @property Confidentiality $confidentiality
 * @property string|null $enacting_clause
 * @property int|null $proposed_effectivity
 * @property string|null $explanatory_note
 * @property int|null $current_reading
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $registered_at
 * @property Carbon|null $published_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $sealed_at
 * @property Carbon|null $deleted_at
 */
#[UseFactory(DocumentFactory::class)]
class Document extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use HasStates;
    use HasUlids;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * `search_vector` is a generated column maintained by PostgreSQL.
     *
     * @var list<string>
     */
    protected $hidden = ['search_vector'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'confidentiality' => Confidentiality::class,
            'status' => DocumentWorkflowStatus::class,
            'current_reading' => 'integer',
            'proposed_effectivity' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'returned_at' => 'datetime',
            'registered_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'sealed_at' => 'datetime',
            'is_public' => 'boolean',
            'tags' => 'array',
            'version_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function title(): CastsAttribute
    {
        return CastsAttribute::make(
            get: fn (string $value) => Str::apa($value),
        );
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** @return BelongsTo<User, $this> */
    public function sealer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sealed_by');
    }

    /** @return BelongsTo<LegislativeSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    /** @return BelongsTo<Committee, $this> */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    /** @return HasOne<DocumentVersion, $this> */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->where('is_current', true);
    }

    /** @return HasOne<DocumentSearchIndex, $this> */
    public function searchIndex(): HasOne
    {
        return $this->hasOne(DocumentSearchIndex::class);
    }

    /** @return HasMany<DocumentMetadata, $this> */
    public function metadata(): HasMany
    {
        return $this->hasMany(DocumentMetadata::class);
    }

    /** @return HasMany<DocumentGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(DocumentGrant::class);
    }

    /** @return HasMany<CommitteeReferral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(CommitteeReferral::class);
    }

    /** @return HasMany<CommitteeReport, $this> */
    public function subjectReports(): HasMany
    {
        return $this->hasMany(CommitteeReport::class, 'subject_document_id');
    }

    /** @return HasMany<AgendaItem, $this> */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(AgendaItem::class);
    }

    /** @return HasOne<Ordinance, $this> */
    public function ordinance(): HasOne
    {
        return $this->hasOne(Ordinance::class);
    }

    /** @return HasOne<Resolution, $this> */
    public function resolution(): HasOne
    {
        return $this->hasOne(Resolution::class);
    }

    /** @return HasMany<Publication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /** @return HasMany<DocumentEmbedding, $this> */
    public function embeddings(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class);
    }

    /** @return MorphMany<PrivateNote, $this> */
    public function privateNotes(): MorphMany
    {
        return $this->morphMany(PrivateNote::class, 'notable');
    }

    /** @return MorphMany<Bookmark, $this> */
    public function bookmarks(): MorphMany
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }

    /**
     * A measure reaches the portal through its numbered ordinance or
     * resolution, so the public always has a citation to quote. Other filings
     * carry no number and publish straight from the document.
     */
    public function awaitsLegislationRecord(): bool
    {
        return $this->document_type->isMeasure()
            && $this->ordinance === null
            && $this->resolution === null;
    }

    /**
     * Documents that {@see awaitsLegislationRecord()} would let through.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWithoutPendingLegislation(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereNotIn('document_type', array_map(
                fn (DocumentType $type): string => $type->value,
                DocumentType::measures(),
            ))
            ->orWhereHas('ordinance')
            ->orWhereHas('resolution'));
    }

    /**
     * Desk copies that may be cited when recording an ordinance or resolution.
     *
     * @param  list<DocumentType>  $types
     * @return Collection<int, $this>
     */
    public static function linkableOfTypes(array $types, ?string $includeId = null, int $limit = 50): Collection
    {
        $documents = static::query()
            ->whereIn('document_type', $types)
            ->latest()
            ->limit($limit)
            ->get(['id', 'title', 'reference_number']);

        if ($includeId !== null && $documents->every(fn (self $document): bool => $document->getKey() !== $includeId)) {
            $current = static::query()
                ->whereKey($includeId)
                ->first(['id', 'title', 'reference_number']);

            if ($current instanceof self) {
                $documents->prepend($current);
            }
        }

        return $documents;
    }

    /**
     * PostgreSQL full-text search over the generated `search_vector` column.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSearch(Builder $query, string $terms): Builder
    {
        return $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$terms]);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_public', true)->whereNotNull('published_at');
    }
}
