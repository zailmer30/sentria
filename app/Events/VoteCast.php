<?php

namespace App\Events;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoteCast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{yes: int, no: int, abstain: int, inhibit: int, total: int}  $tallies
     */
    public function __construct(
        public LegislativeSession $session,
        public AgendaItem $agendaItem,
        public int $votingRound,
        public array $tallies,
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
        return 'VoteCast';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->getKey(),
            'agenda_item_id' => $this->agendaItem->getKey(),
            'voting_round' => $this->votingRound,
            'tallies' => $this->tallies,
        ];
    }
}
