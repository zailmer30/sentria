<?php

namespace App\Http\Requests\Sessions;

use App\Models\SessionConversation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSessionChatParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $conversation = $this->route('conversation');

        return $user instanceof User
            && $conversation instanceof SessionConversation
            && $user->can('updateParticipants', $conversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'ulid', 'exists:users,id', 'distinct'],
        ];
    }
}
