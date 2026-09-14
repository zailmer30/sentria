<?php

namespace App\Http\Requests\Sessions;

use App\Enums\AttendanceStatus;
use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var LegislativeSession|null $session */
        $session = $this->route('session');

        return $session !== null && ($this->user()?->can('recordAttendance', $session) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1'],
            'records.*.user_id' => ['required', 'ulid', 'exists:users,id'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
