<?php

namespace App\Http\Requests\Minutes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmMinutesSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'suggestion_id' => ['required', 'string', 'max:26'],
            'type' => ['required', Rule::in(['motion', 'action_item'])],
        ];
    }
}
