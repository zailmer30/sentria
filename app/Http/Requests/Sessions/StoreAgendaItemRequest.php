<?php

namespace App\Http\Requests\Sessions;

use App\Models\AgendaItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AgendaItem::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:40'],
            'document_id' => ['nullable', 'ulid', 'exists:documents,id'],
            'committee_id' => ['nullable', 'ulid', 'exists:committees,id'],
            'presented_by' => ['nullable', 'ulid', 'exists:users,id'],
            'time_allotment_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'requires_vote' => ['sometimes', 'boolean'],
        ];
    }
}
