<?php

namespace App\Jobs\Sessions;

use App\Contracts\AI\TranscriptionService;
use App\Enums\ChamberFeed;
use App\Events\TranscriptUpdated;
use App\Models\AgendaItem;
use App\Models\Transcript;
use App\Services\AI\TranscriptionError;
use App\Services\Sessions\ChamberChannelService;
use App\Services\Sessions\TranscriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessChamberChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $transcriptId,
        public int $channelIndex,
        public int $seq,
        public int $startedAtMs,
        public string $path,
        public string $disk,
    ) {}

    public function handle(
        TranscriptionService $transcription,
        TranscriptService $transcripts,
        ChamberChannelService $channels,
    ): void {
        $transcript = Transcript::query()
            ->with(['session', 'agendaItem'])
            ->find($this->transcriptId);

        if ($transcript === null) {
            return;
        }

        $session = $transcript->session;

        if ($session === null) {
            return;
        }

        $absolutePath = Storage::disk($this->disk)->path($this->path);

        if (! is_readable($absolutePath)) {
            return;
        }

        $feed = $session->chamberFeed();
        $mixer = $feed === ChamberFeed::MixerMix;
        $channel = null;

        if (! $mixer) {
            $snapshot = is_array($transcript->channel_map_snapshot) ? $transcript->channel_map_snapshot : null;
            $channel = $channels->resolveChannel($snapshot, $this->channelIndex);

            if ($channel === null) {
                return;
            }
        }

        $keywords = $this->agendaKeywords($transcript->agendaItem);
        $offsetSeconds = $this->startedAtMs / 1000;

        try {
            $result = $transcription->transcribeSessionAudio(
                $absolutePath,
                $this->guessMime($absolutePath),
                $keywords,
                'auto',
                ['source' => $mixer ? 'chamber_mix' : 'chamber'],
            );
        } catch (Throwable $exception) {
            $message = TranscriptionError::message($exception);

            Log::warning('Chamber chunk transcription failed.', [
                'transcript_id' => $this->transcriptId,
                'channel_index' => $this->channelIndex,
                'seq' => $this->seq,
                'started_at_ms' => $this->startedAtMs,
                'message' => $message,
            ]);

            $transcript->update(['processing_error' => $message]);
            event(new TranscriptUpdated($session, $transcript->refresh(), null, $transcript->status));

            throw $exception;
        }

        $baseIndex = ($this->channelIndex * 1_000_000_000) + ($this->startedAtMs * 100);

        foreach ($result->segments as $i => $segment) {
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $payload = [
                'index' => $baseIndex + $i,
                'start' => $offsetSeconds + (float) ($segment['start'] ?? 0),
                'end' => $offsetSeconds + (float) ($segment['end'] ?? 0),
                'text' => $text,
                'confidence' => isset($segment['confidence']) ? (float) $segment['confidence'] : null,
                'language' => isset($segment['language']) ? (string) $segment['language'] : $result->language,
            ];

            if ($mixer) {
                $payload['speaker'] = null;
                $payload['speaker_id'] = null;
                $payload['attributed'] = false;
            } else {
                $payload['speaker'] = $channel['speaker'];
                $payload['speaker_id'] = $channel['user_id'];
                $payload['attributed'] = true;
            }

            $transcripts->appendLiveSegment($transcript, $payload);

            $transcript->refresh();
        }

        $status = ['status' => 'processing', 'processing_error' => null];

        if ($result->provider !== null) {
            $status['provider'] = $result->provider;
        }

        if ($result->model !== null) {
            $status['model'] = $result->model;
        }

        if ($transcript->status !== 'processing' || $result->provider !== null || $result->model !== null) {
            $transcript->update($status);
        }
    }

    /**
     * @return list<string>
     */
    private function agendaKeywords(?AgendaItem $agendaItem): array
    {
        if ($agendaItem === null) {
            return [];
        }

        return array_values(array_filter([
            $agendaItem->title,
            $agendaItem->item_number,
        ], fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    private function guessMime(string $absolutePath): string
    {
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($absolutePath);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return 'audio/wav';
    }
}
