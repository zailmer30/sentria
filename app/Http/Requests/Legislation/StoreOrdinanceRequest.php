<?php

namespace App\Http\Requests\Legislation;

use App\Enums\DocumentType;
use App\Models\Ordinance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ordinance::class) ?? false;
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
                    DocumentType::ordinanceMeasures(),
                )),
                'unique:ordinances,document_id',
            ],
            'ordinance_number' => ['nullable', 'string', 'max:60', Rule::unique('ordinances', 'ordinance_number')],
            'title' => ['required', 'string', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(['draft', 'pending', 'enacted', 'vetoed', 'repealed'])],
            'enacted_on' => ['nullable', 'date'],
            'approving_authority' => ['nullable', 'string', 'max:255'],
            'effectivity_date' => ['nullable', 'date'],
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
