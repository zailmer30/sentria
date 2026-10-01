<?php

namespace App\Http\Requests\Documents;

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('sentria.documents.max_upload_size_kb', 51200);
        /** @var list<string> $allowedMime */
        $allowedMime = config('sentria.documents.allowed_mime_types', []);
        $measure = $this->isMeasure();
        $ordinance = $this->isOrdinanceMeasure();

        return [
            'title' => ['required', 'string', 'max:500'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'confidentiality' => ['nullable', Rule::enum(Confidentiality::class)],
            'abstract' => ['nullable', 'string', 'max:5000'],
            'external_author' => ['required', 'string', 'max:255'],
            'enacting_clause' => [$measure ? 'required' : 'nullable', 'string', 'max:5000'],
            'explanatory_note' => [$ordinance ? 'required' : 'nullable', 'string', 'max:10000'],
            'committee_id' => ['nullable', 'ulid', 'exists:committees,id'],
            'session_id' => ['nullable', 'ulid', 'exists:sessions,id'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'change_summary' => ['nullable', 'string', 'max:1000'],
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:'.implode(',', $allowedMime),
            ],
        ];
    }

    private function isMeasure(): bool
    {
        $type = DocumentType::tryFrom((string) $this->input('document_type'));

        return $type?->isMeasure() ?? false;
    }

    private function isOrdinanceMeasure(): bool
    {
        return DocumentType::tryFrom((string) $this->input('document_type'))?->isOrdinanceMeasure() ?? false;
    }
}
