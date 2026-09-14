<?php

namespace App\Events;

use App\Models\LegislativeSession;
use App\Models\Motion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MotionRecorded implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LegislativeSession $session,
        public Motion $motion,
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
        return 'MotionRecorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->motion->loadMissing(['mover', 'seconder']);

        return [
            'session_id' => $this->session->getKey(),
            'motion' => [
                'id' => $this->motion->getKey(),
                'text' => $this->motion->text,
                'type' => $this->motion->type,
                'status' => $this->motion->status,
                'mover' => $this->motion->mover?->display_name,
                'seconder' => $this->motion->seconder?->display_name,
                'moved_at' => $this->motion->moved_at instanceof Carbon
                    ? $this->motion->moved_at->toIso8601String()
                    : null,
                'agenda_item_id' => $this->motion->agenda_item_id,
            ],
        ];
    }
}
