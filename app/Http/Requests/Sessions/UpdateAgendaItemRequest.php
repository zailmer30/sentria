<?php

namespace App\Http\Requests\Sessions;

use App\Models\AgendaItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AgendaItem|null $item */
        $item = $this->route('agendaItem');

        return $item !== null && ($this->user()?->can('update', $item) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', 'max:40'],
            'document_id' => ['nullable', 'ulid', 'exists:documents,id'],
            'committee_id' => ['nullable', 'ulid', 'exists:committees,id'],
            'presented_by' => ['nullable', 'ulid', 'exists:users,id'],
            'time_allotment_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'requires_vote' => ['sometimes', 'boolean'],
        ];
    }
}
