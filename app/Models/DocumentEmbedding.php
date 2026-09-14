<?php

namespace App\Models;

use Database\Factories\DocumentEmbeddingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

/**
 * One embedded chunk of a document version. `confidentiality` and `is_public`
 * are denormalized here on purpose: the authorization filter must run inside
 * the retrieval query, not as a post-filter on returned chunks.
 */
#[UseFactory(DocumentEmbeddingFactory::class)]
class DocumentEmbedding extends Model
{
    /** @use HasFactory<DocumentEmbeddingFactory> */
    use HasFactory;

    use HasNeighbors;
    use HasUlids;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => Vector::class,
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'page_number' => 'integer',
            'char_start' => 'integer',
            'char_end' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
