<?php

namespace App\Http\Requests\Committees;

use App\Models\CommitteeReferral;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommitteeReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CommitteeReferral::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_id' => ['required', 'ulid', 'exists:documents,id'],
            'committee_id' => ['required', 'ulid', 'exists:committees,id'],
            'session_id' => ['nullable', 'ulid', 'exists:sessions,id'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
