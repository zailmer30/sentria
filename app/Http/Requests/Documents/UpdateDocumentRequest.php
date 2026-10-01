<?php

namespace App\Http\Requests\Documents;

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document !== null && ($this->user()?->can('update', $document) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Document $document */
        $document = $this->route('document');
        $measure = $this->isMeasure();
        $ordinance = $this->isOrdinanceMeasure();

        return [
            'title' => ['required', 'string', 'max:500'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'confidentiality' => ['required', Rule::enum(Confidentiality::class)],
            'abstract' => ['nullable', 'string', 'max:5000'],
            'external_author' => ['required', 'string', 'max:255'],
            'enacting_clause' => [$measure ? 'required' : 'nullable', 'string', 'max:5000'],
            'explanatory_note' => [$ordinance ? 'required' : 'nullable', 'string', 'max:10000'],
            'committee_id' => ['nullable', 'ulid', 'exists:committees,id'],
            'session_id' => ['nullable', 'ulid', 'exists:sessions,id'],
            'reference_number' => [
                $measure ? 'required' : 'nullable',
                'string',
                'max:80',
                Rule::unique('documents', 'reference_number')->ignore($document->getKey()),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
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
