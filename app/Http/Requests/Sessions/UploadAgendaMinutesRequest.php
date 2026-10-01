<?php

namespace App\Http\Requests\Sessions;

use App\Models\AgendaItem;
use Illuminate\Foundation\Http\FormRequest;

class UploadAgendaMinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AgendaItem|null $item */
        $item = $this->route('agendaItem');

        return $item !== null
            && ($this->user()?->can('update', $item) ?? false)
            && ($this->user()?->can('documents.create') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('sentria.documents.max_upload_size_kb', 51200);

        return [
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:application/pdf'],
            'title' => ['nullable', 'string', 'max:500'],
            'of_session_id' => ['nullable', 'ulid', 'exists:sessions,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('of_session_id') === '') {
            $this->merge(['of_session_id' => null]);
        }

        if ($this->input('title') === '') {
            $this->merge(['title' => null]);
        }
    }
}
