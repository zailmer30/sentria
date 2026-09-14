<?php

namespace App\Http\Requests\Sessions;

use App\Enums\SessionType;
use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LegislativeSession::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_number' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(SessionType::class)],
            'legislative_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
            'venue' => ['nullable', 'string', 'max:255'],
            'presiding_officer_id' => ['nullable', 'ulid', 'exists:users,id'],
            'secretary_id' => ['nullable', 'ulid', 'exists:users,id'],
            'seated_member_count' => ['nullable', 'integer', 'min:1'],
            'is_public' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
