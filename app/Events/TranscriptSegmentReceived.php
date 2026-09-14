<?php

namespace App\Events;

use App\Models\LegislativeSession;
use App\Models\Transcript;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscriptSegmentReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{index: int, start: float, end: float, speaker?: string|null, speaker_id?: string|null, text: string, confidence?: float|null, language?: string|null}  $segment
     */
    public function __construct(
        public LegislativeSession $session,
        public Transcript $transcript,
        public array $segment,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('session-transcript.'.$this->session->getKey()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TranscriptSegmentReceived';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->getKey(),
            'transcript_id' => $this->transcript->getKey(),
            'segment' => $this->segment,
            'agenda_item_id' => $this->transcript->agenda_item_id,
        ];
    }
}
