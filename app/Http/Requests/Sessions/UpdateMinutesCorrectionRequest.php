<?php

namespace App\Http\Requests\Sessions;

use App\Models\MinutesCorrection;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMinutesCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MinutesCorrection|null $correction */
        $correction = $this->route('correction');

        return $correction instanceof MinutesCorrection
            && ($this->user()?->can('update', $correction) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'as_written' => ['required', 'string', 'max:2000'],
            'should_read' => ['required', 'string', 'max:2000'],
            'page_number' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('page_number') === '' || $this->input('page_number') === null) {
            $this->merge(['page_number' => null]);
        }
    }
}
