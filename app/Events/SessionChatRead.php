<?php

namespace App\Events;

use App\Models\SessionConversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SessionChatRead implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public SessionConversation $conversation,
        public User $reader,
        public Carbon $lastReadAt,
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
        return 'SessionChatRead';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->getKey(),
            'session_id' => $this->conversation->session_id,
            'user_id' => $this->reader->getKey(),
            'last_read_at' => $this->lastReadAt->toIso8601String(),
        ];
    }
}
