<?php

namespace App\Http\Requests\Sessions;

use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;

class OpenVotingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof LegislativeSession
            && ($this->user()?->can('openVoting', $session) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
            'motion_id' => ['sometimes', 'nullable', 'ulid', 'exists:motions,id'],
            'silent' => ['sometimes', 'boolean'],
        ];
    }
}
