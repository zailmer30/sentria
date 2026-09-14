<?php

namespace App\Http\Requests\Sessions;

use App\Models\AgendaItem;
use Illuminate\Foundation\Http\FormRequest;

class BindAgendaDocumentsRequest extends FormRequest
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
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'ulid', 'exists:documents,id'],
        ];
    }
}
