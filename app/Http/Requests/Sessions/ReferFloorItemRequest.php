<?php

namespace App\Http\Requests\Sessions;

use Illuminate\Foundation\Http\FormRequest;

class ReferFloorItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.refer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
            'committee_id' => ['required', 'ulid', 'exists:committees,id'],
        ];
    }
}
