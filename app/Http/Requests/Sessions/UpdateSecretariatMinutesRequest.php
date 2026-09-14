<?php

namespace App\Http\Requests\Sessions;

use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSecretariatMinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof LegislativeSession
            && ($this->user()?->can('recordMinutes', $session) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'secretariat_minutes' => ['nullable', 'string', 'max:200000'],
        ];
    }
}
