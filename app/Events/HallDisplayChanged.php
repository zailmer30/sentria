<?php

namespace App\Events;

use App\Models\LegislativeSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HallDisplayChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{zoom: float, page: int, relative_x: float, relative_y: float}|null  $view
     */
    public function __construct(
        public LegislativeSession $session,
        public string $stage,
        public ?string $agendaItemId = null,
        public ?array $view = null,
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
        return 'HallDisplayChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->getKey(),
            'stage' => $this->stage,
            'agenda_item_id' => $this->agendaItemId,
            'view' => $this->view,
        ];
    }
}
