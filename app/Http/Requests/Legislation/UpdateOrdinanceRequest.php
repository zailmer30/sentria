<?php

namespace App\Http\Requests\Legislation;

use App\Enums\DocumentType;
use App\Models\Ordinance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrdinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ordinance = $this->route('ordinance');

        return $ordinance !== null && ($this->user()?->can('update', $ordinance) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Ordinance $ordinance */
        $ordinance = $this->route('ordinance');

        return [
            'document_id' => [
                'required',
                'ulid',
                Rule::exists('documents', 'id')->whereIn('document_type', array_map(
                    fn (DocumentType $type): string => $type->value,
                    DocumentType::ordinanceMeasures(),
                )),
                Rule::unique('ordinances', 'document_id')->ignore($ordinance->getKey()),
            ],
            'ordinance_number' => ['required', 'string', 'max:60', Rule::unique('ordinances', 'ordinance_number')->ignore($ordinance->getKey())],
            'series_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'title' => ['required', 'string', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(['draft', 'pending', 'enacted', 'vetoed', 'repealed'])],
            'enacted_on' => ['nullable', 'date'],
            'approving_authority' => ['nullable', 'string', 'max:255'],
            'approved_on' => ['nullable', 'date'],
            'vetoed_on' => ['nullable', 'date'],
            'veto_overridden_on' => ['nullable', 'date'],
            'effectivity_date' => ['nullable', 'date'],
            'publication_date' => ['nullable', 'date'],
            'publication_medium' => ['nullable', 'string', 'max:255'],
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
            'document_id.exists' => __('legislation.document_must_be_ordinance'),
        ];
    }
}
