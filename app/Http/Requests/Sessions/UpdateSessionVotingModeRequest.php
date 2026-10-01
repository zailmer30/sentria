<?php

namespace App\Http\Requests\Sessions;

use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionVotingModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof LegislativeSession
            && ($this->user()?->can('updateVotingMode', $session) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'defer_heading_votes' => ['required', 'boolean'],
        ];
    }
}
