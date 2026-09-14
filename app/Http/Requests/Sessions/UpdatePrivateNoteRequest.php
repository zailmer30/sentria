<?php

namespace App\Http\Requests\Sessions;

use App\Models\PrivateNote;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PrivateNote|null $privateNote */
        $privateNote = $this->route('privateNote');

        return $privateNote !== null && ($this->user()?->can('update', $privateNote) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'page_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
