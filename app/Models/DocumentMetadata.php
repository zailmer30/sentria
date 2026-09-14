<?php

namespace App\Models;

use Database\Factories\DocumentMetadataFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('document_metadata')]
#[UseFactory(DocumentMetadataFactory::class)]
class DocumentMetadata extends Model
{
    /** @use HasFactory<DocumentMetadataFactory> */
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
            'value_json' => 'array',
            'confidence' => 'float',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isAiDerived(): bool
    {
        return in_array($this->source, ['ai', 'ocr'], true);
    }
}
