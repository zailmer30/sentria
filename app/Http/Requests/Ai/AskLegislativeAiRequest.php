<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class AskLegislativeAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ai.use') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:2000'],
            'conversation_id' => ['nullable', 'string', 'ulid'],
            'document_slug' => ['nullable', 'string', 'max:255'],
        ];
    }
}
