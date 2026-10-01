<?php

namespace App\Http\Requests\Legislation;

use App\Enums\DocumentType;
use App\Models\Resolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resolution = $this->route('resolution');

        return $resolution !== null && ($this->user()?->can('update', $resolution) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Resolution $resolution */
        $resolution = $this->route('resolution');

        return [
            'document_id' => [
                'required',
                'ulid',
                Rule::exists('documents', 'id')->whereIn('document_type', array_map(
                    fn (DocumentType $type): string => $type->value,
                    DocumentType::resolutionMeasures(),
                )),
                Rule::unique('resolutions', 'document_id')->ignore($resolution->getKey()),
            ],
            'resolution_number' => ['required', 'string', 'max:60', Rule::unique('resolutions', 'resolution_number')->ignore($resolution->getKey())],
            'series_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'title' => ['required', 'string', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string', Rule::in(['draft', 'pending', 'adopted', 'withdrawn'])],
            'adopted_on' => ['nullable', 'date'],
            'effectivity_date' => ['nullable', 'date'],
            'transmitted_on' => ['nullable', 'date'],
            'transmitted_to' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_id.exists' => __('legislation.document_must_be_resolution'),
        ];
    }
}
