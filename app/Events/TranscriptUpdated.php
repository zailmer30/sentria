<?php

namespace App\Events;

use App\Models\LegislativeSession;
use App\Models\Transcript;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscriptUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<array{index: int, start: float, end: float, speaker?: string|null, speaker_id?: string|null, text: string, confidence?: float|null, language?: string|null}>|null  $segments
     */
    public function __construct(
        public LegislativeSession $session,
        public Transcript $transcript,
        public ?array $segments = null,
        public ?string $status = null,
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
        return 'TranscriptUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->getKey(),
            'transcript_id' => $this->transcript->getKey(),
            'status' => $this->status ?? $this->transcript->status,
            'error' => $this->transcript->processing_error,
            'segments' => $this->segments,
            'full_text' => $this->transcript->full_text,
            'agenda_item_id' => $this->transcript->agenda_item_id,
        ];
    }
}
