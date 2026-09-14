<?php

namespace App\Events;

use App\Models\LegislativeSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live PDF viewport on the hall board. Broadcast immediately so zoom/scroll
 * from the secretariat console feels locked to the chamber display.
 */
class HallDisplayViewChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{zoom: float, page: int, relative_x: float, relative_y: float}|null  $view
     */
    public function __construct(
        public LegislativeSession $session,
        public ?array $view,
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
        return 'HallDisplayViewChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->getKey(),
            'view' => $this->view,
        ];
    }
}
