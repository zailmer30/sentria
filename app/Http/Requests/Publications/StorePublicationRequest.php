<?php

namespace App\Http\Requests\Publications;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publications.review') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_id' => [
                'required',
                'string',
                Rule::exists('documents', 'id')->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('document_id')) {
                return;
            }

            $document = Document::query()->find($this->string('document_id')->toString());

            if ($document?->awaitsLegislationRecord() === true) {
                $validator->errors()->add('document_id', __('publications.requires_legislation_record'));
            }
        });
    }

    public function document(): Document
    {
        /** @var Document $document */
        $document = Document::query()->findOrFail($this->validated('document_id'));

        return $document;
    }
}
