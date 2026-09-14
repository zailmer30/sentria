<?php

namespace App\Http\Requests\Committees;

use App\Models\Committee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommitteeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $committee = $this->route('committee');

        return $committee instanceof Committee
            && ($this->user()?->can('manageMembers', $committee) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $committee = $this->route('committee');
        $committeeId = $committee instanceof Committee ? $committee->getKey() : null;

        return [
            'user_id' => [
                'required',
                'ulid',
                'exists:users,id',
                Rule::unique('committee_members', 'user_id')->where('committee_id', $committeeId),
            ],
            'position' => ['required', 'string', Rule::in(['chair', 'vice-chair', 'member'])],
            'appointed_on' => ['nullable', 'date'],
        ];
    }
}
