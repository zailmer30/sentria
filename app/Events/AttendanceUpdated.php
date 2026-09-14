<?php

namespace App\Events;

use App\Models\LegislativeSession;
use App\Services\Sessions\QuorumDisplayDto;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LegislativeSession $session,
        public QuorumDisplayDto $quorum,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('session.'.$this->session->getKey()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AttendanceUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->getKey(),
            'quorum' => $this->quorum->toArray(),
        ];
    }
}
