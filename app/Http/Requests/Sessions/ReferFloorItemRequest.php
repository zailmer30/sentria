<?php

namespace App\Http\Requests\Sessions;

use App\Http\Requests\Concerns\ResolvesCommitteeReferralInput;
use Illuminate\Foundation\Http\FormRequest;

class ReferFloorItemRequest extends FormRequest
{
    use ResolvesCommitteeReferralInput;

    public function authorize(): bool
    {
        return $this->user()?->can('documents.refer') ?? false;
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
        return [
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
            ...$this->committeeReferralDetailRules(true),
        ];
    }
}
