<?php

namespace App\Events;

use App\Models\SessionConversation;
use App\Models\SessionMessage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public SessionConversation $conversation,
        public SessionMessage $message,
        public User $sender,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('session-chat.'.$this->conversation->getKey()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'SessionChatMessageSent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->getKey(),
            'session_id' => $this->conversation->session_id,
            'message' => [
                'id' => $this->message->getKey(),
                'user_id' => $this->sender->getKey(),
                'display_name' => $this->sender->display_name,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
        ];
    }
}
