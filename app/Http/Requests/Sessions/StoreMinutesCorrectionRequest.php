<?php

namespace App\Http\Requests\Sessions;

use App\Models\AgendaItem;
use App\Models\MinutesCorrection;
use Illuminate\Foundation\Http\FormRequest;

class StoreMinutesCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AgendaItem|null $item */
        $item = $this->route('agendaItem');

        return $item !== null && ($this->user()?->can('create', [MinutesCorrection::class, $item]) ?? false);
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
