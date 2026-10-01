<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SessionChatInboxUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  list<string>  $recipientIds
     */
    public function __construct(
        public string $conversationId,
        public string $sessionId,
        public array $recipientIds,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            static fn (string $id): PrivateChannel => new PrivateChannel('App.Models.User.'.$id),
            $this->recipientIds,
        );
    }

    public function broadcastAs(): string
    {
        return 'SessionChatInboxUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'session_id' => $this->sessionId,
        ];
    }
}
