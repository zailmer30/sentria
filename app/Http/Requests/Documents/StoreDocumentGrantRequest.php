<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document !== null && ($this->user()?->can('grantAccess', $document) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'ulid', 'exists:users,id', 'required_without_all:role_id,committee_id'],
            'role_id' => ['nullable', 'ulid', 'exists:roles,id', 'required_without_all:user_id,committee_id'],
            'committee_id' => ['nullable', 'ulid', 'exists:committees,id', 'required_without_all:user_id,role_id'],
            'ability' => ['nullable', 'string', Rule::in(['view', 'download'])],
            'expires_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
