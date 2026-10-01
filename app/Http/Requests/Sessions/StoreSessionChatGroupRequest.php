<?php

namespace App\Http\Requests\Sessions;

use App\Models\LegislativeSession;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSessionChatGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $session = $this->route('session');

        return $user instanceof User
            && $session instanceof LegislativeSession
            && $user->can('useChat', $session);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'participant_ids' => ['required', 'array', 'min:2'],
            'participant_ids.*' => ['required', 'ulid', 'exists:users,id', 'distinct'],
        ];
    }
}
