<?php

namespace App\Http\Requests\Sessions;

use App\Enums\VoteChoice;
use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CastVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof LegislativeSession
            && ($this->user()?->can('castVote', $session) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
            'choice' => ['required', 'string', Rule::enum(VoteChoice::class)],
            'voting_round' => ['required', 'integer', 'min:1'],
        ];
    }
}
