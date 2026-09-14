<?php

namespace App\Http\Requests\Sessions;

use App\Models\Bookmark;
use App\Models\Document;
use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Bookmark::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bookmarkable_type' => ['required', 'string', Rule::in([LegislativeSession::class, Document::class])],
            'bookmarkable_id' => ['required', 'ulid'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
