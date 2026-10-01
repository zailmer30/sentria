<?php

namespace App\Http\Requests\Sessions;

use App\Models\SessionConversation;
use App\Models\User;
use App\Services\Sessions\SessionChatService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSessionChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $conversation = $this->route('conversation');

        return $user instanceof User
            && $conversation instanceof SessionConversation
            && $user->can('send', $conversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.SessionChatService::BODY_MAX],
        ];
    }
}
