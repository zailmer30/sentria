<?php

namespace App\Http\Requests\Minutes;

use App\Models\Minutes;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $minutes = $this->route('minute');

        return $minutes instanceof Minutes
            && ($this->user()?->can('update', $minutes) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string'],
            'content_html' => ['nullable', 'string'],
        ];
    }
}
