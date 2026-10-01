<?php

namespace App\Http\Requests\Sessions;

use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;

class DestroySessionGuestRequest extends FormRequest
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
        return [];
    }
}
