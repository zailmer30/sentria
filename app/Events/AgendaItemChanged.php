<?php

namespace App\Events;

use App\Http\Resources\SessionResource;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Services\Sessions\AgendaService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgendaItemChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LegislativeSession $session,
        public ?AgendaItem $currentItem,
        public ?AgendaItem $nextItem = null,
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
        return 'AgendaItemChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $previous = app(AgendaService::class)->previousCompletedItem($this->session);

        return [
            'session_id' => $this->session->getKey(),
            'current_item' => $this->currentItem ? SessionResource::agendaItem($this->currentItem) : null,
            'next_item' => $this->nextItem ? SessionResource::agendaItem($this->nextItem) : null,
            'previous_item' => $previous ? SessionResource::agendaItem($previous) : null,
        ];
    }
}
