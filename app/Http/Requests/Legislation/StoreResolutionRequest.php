<?php

namespace App\Http\Requests\Legislation;

use App\Enums\DocumentType;
use App\Models\Resolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Resolution::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_id' => [
                'required',
                'ulid',
                Rule::exists('documents', 'id')->whereIn('document_type', array_map(
                    fn (DocumentType $type): string => $type->value,
                    DocumentType::resolutionMeasures(),
                )),
                'unique:resolutions,document_id',
            ],
            'resolution_number' => ['required', 'string', 'max:60', 'unique:resolutions,resolution_number'],
            'series_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'title' => ['required', 'string', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string', Rule::in(['draft', 'pending', 'adopted', 'withdrawn'])],
            'adopted_on' => ['nullable', 'date'],
            'effectivity_date' => ['nullable', 'date'],
            'transmitted_on' => ['nullable', 'date'],
            'transmitted_to' => ['nullable', 'string', 'max:255'],
            'lce_sp_required' => ['sometimes', 'boolean'],
            'sp_submitted_on' => ['nullable', 'date'],
            'sp_reviewed_on' => ['nullable', 'date'],
            'sp_result' => ['nullable', 'string', Rule::in(['consistent', 'invalid', 'presumed'])],
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
