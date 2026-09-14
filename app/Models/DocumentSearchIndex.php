<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSearchIndex extends Model
{
    use HasUlids;

    protected $table = 'document_search_indexes';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['search_vector'];

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
