<?php

namespace App\Http\Requests\Sessions;

use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;

class StartRecessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof LegislativeSession
            && ($this->user()?->can('suspend', $session) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function durationMinutes(): int
    {
        return (int) $this->integer('duration_minutes', 10);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('duration_minutes')) {
            $this->merge(['duration_minutes' => 10]);
        }
    }
}
