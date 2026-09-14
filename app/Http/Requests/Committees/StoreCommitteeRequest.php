<?php

namespace App\Http\Requests\Committees;

use App\Models\Committee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommitteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Committee::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:30', 'unique:committees,code'],
            'type' => ['required', 'string', Rule::in(['standing', 'special', 'ad-hoc'])],
            'mandate' => ['nullable', 'string', 'max:5000'],
            'established_on' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
