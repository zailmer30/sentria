<?php

namespace App\Http\Requests\Sessions;

use App\Models\LegislativeSession;
use App\Services\Sessions\SessionGuestService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSessionGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var LegislativeSession|null $session */
        $session = $this->route('session');

        return $session !== null && ($this->user()?->can('recordAttendance', $session) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => SessionGuestService::blankToNull($this->input('name')),
            'organization' => SessionGuestService::blankToNull($this->input('organization')),
            'speaking_topic' => SessionGuestService::blankToNull($this->input('speaking_topic')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'organization' => ['nullable', 'string', 'max:200'],
            'speaking_topic' => ['nullable', 'string', 'max:500'],
        ];
    }
}
