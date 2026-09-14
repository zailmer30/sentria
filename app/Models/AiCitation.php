<?php

namespace App\Models;

use Database\Factories\AiCitationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links an AI answer back to the exact source passage, so a reader can verify
 * the claim against the original document.
 */
#[UseFactory(AiCitationFactory::class)]
class AiCitation extends Model
{
    /** @use HasFactory<AiCitationFactory> */
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
            'similarity' => 'float',
            'rank' => 'integer',
            'page_number' => 'integer',
        ];
    }

    /** @return BelongsTo<AiMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
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

    /** @return BelongsTo<DocumentEmbedding, $this> */
    public function embedding(): BelongsTo
    {
        return $this->belongsTo(DocumentEmbedding::class, 'document_embedding_id');
    }
}
