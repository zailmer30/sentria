<?php

namespace App\Http\Requests\Committees;

use App\Models\CommitteeReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommitteeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CommitteeReport::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $committeeId = $this->string('committee_id')->toString();

        return [
            'committee_referral_id' => [
                'required',
                'ulid',
                Rule::exists('committee_referrals', 'id')->where('committee_id', $committeeId),
            ],
            'committee_id' => ['required', 'ulid', 'exists:committees,id'],
            'subject_document_id' => ['nullable', 'ulid', 'exists:documents,id'],
            'recommendation' => ['required', 'string', Rule::in(['approve', 'disapprove', 'amend', 'defer', 'no-action'])],
            'findings' => ['nullable', 'string', 'max:10000'],
            'recommendation_notes' => ['nullable', 'string', 'max:10000'],
            'report_number' => ['nullable', 'string', 'max:60', 'unique:committee_reports,report_number'],
        ];
    }
}
