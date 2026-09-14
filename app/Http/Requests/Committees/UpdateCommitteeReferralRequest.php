<?php

namespace App\Http\Requests\Committees;

use App\Models\CommitteeReferral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommitteeReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        $referral = $this->route('referral');

        return $referral instanceof CommitteeReferral
            && ($this->user()?->can('update', $referral) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $referral = $this->route('referral');
        $allowed = $referral instanceof CommitteeReferral
            ? $referral->successors()
            : [];

        return [
            'status' => ['required', 'string', Rule::in($allowed)],
            'outcome_notes' => [
                Rule::requiredIf(in_array($this->input('status'), ['returned', 'closed'], true)),
                'nullable',
                'string',
                'max:5000',
            ],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
