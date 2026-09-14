<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document !== null && ($this->user()?->can('uploadVersion', $document) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('sentria.documents.max_upload_size_kb', 51200);
        /** @var list<string> $allowedMime */
        $allowedMime = config('sentria.documents.allowed_mime_types', []);

        return [
            'change_summary' => ['nullable', 'string', 'max:1000'],
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:'.implode(',', $allowedMime),
            ],
        ];
    }
}
