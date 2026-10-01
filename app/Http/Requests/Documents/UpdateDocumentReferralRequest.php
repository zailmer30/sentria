<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\Concerns\ResolvesCommitteeReferralInput;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentReferralRequest extends FormRequest
{
    use ResolvesCommitteeReferralInput;

    public function authorize(): bool
    {
        $document = $this->route('document');
        $user = $this->user();

        return $document instanceof Document
            && ($user?->can('documents.refer') ?? false)
            && ($user?->can('view', $document) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCommitteeReferralInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->committeeReferralDetailRules(true);
    }
}
