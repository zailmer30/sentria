<?php

namespace App\Events;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Motion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VotingOpened implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LegislativeSession $session,
        public AgendaItem $agendaItem,
        public ?Motion $motion,
        public int $votingRound,
        public bool $electronicIsBinding = false,
        public bool $silent = false,
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
        return 'VotingOpened';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->getKey(),
            'agenda_item_id' => $this->agendaItem->getKey(),
            'motion_id' => $this->motion?->getKey(),
            'voting_round' => $this->votingRound,
            'electronic_is_binding' => $this->electronicIsBinding,
            'silent' => $this->silent,
        ];
    }
}
