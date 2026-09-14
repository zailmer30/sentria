<?php

namespace App\Http\Requests\Minutes;

use App\Models\Minutes;
use Illuminate\Foundation\Http\FormRequest;

class StoreMinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Minutes::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'ulid', 'exists:sessions,id'],
            'content' => ['nullable', 'string'],
        ];
    }
}
