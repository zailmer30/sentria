<?php

namespace App\Jobs\Sessions;

use App\Contracts\AI\TranscriptionService;
use App\Events\TranscriptSegmentReceived;
use App\Events\TranscriptUpdated;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Services\AI\TranscriptionError;
use App\Services\Sessions\TranscriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessSessionTranscriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $transcriptId,
    ) {}

    public function handle(TranscriptionService $transcription, TranscriptService $transcripts): void
    {
        $transcript = Transcript::query()
            ->with(['session', 'agendaItem'])
            ->find($this->transcriptId);

        if ($transcript === null) {
            return;
        }

        $transcript->update(['status' => 'processing']);

        $session = $transcript->session;
        abort_unless($session !== null, 404);

        $disk = (string) $transcript->disk;
        $path = (string) $transcript->audio_path;

        if ($disk === '' || $path === '') {
            $this->markFailed($transcript, $session, 'Audio file is missing; transcription cannot run.');

            return;
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $mime = $this->guessMime($absolutePath);

        $keywords = $this->agendaKeywords($transcript->agendaItem);

        try {
            $result = $transcription->transcribeSessionAudio(
                $absolutePath,
                $mime,
                $keywords,
                (string) config('sentria.transcription.language_mode', 'auto'),
                ['source' => 'upload'],
            );

            $segments = $transcripts->stampOriginalsOnMany($result->segments);
            $broadcast = $transcripts->typedSegments($segments);
            $transcripts->discardEdits($transcript);

            $transcript->update([
                'status' => 'completed',
                'processing_error' => null,
                'full_text' => $result->fullText,
                'segments' => $segments,
                'average_confidence' => $result->averageConfidence,
                'duration_seconds' => $result->durationSeconds,
                'provider' => $result->provider ?? config('sentria.transcription.driver'),
                'model' => $result->model ?? config('sentria.transcription.model'),
                'ended_at' => now(),
            ]);

            $transcript->refresh();

            foreach ($broadcast as $segment) {
                event(new TranscriptSegmentReceived($session, $transcript, $segment));
            }

            event(new TranscriptUpdated($session, $transcript, $broadcast, 'completed'));
        } catch (Throwable $exception) {
            $this->markFailed($transcript, $session, TranscriptionError::message($exception));
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

        $keywords = array_filter([
            $agendaItem->title,
            $agendaItem->item_number,
        ]);

        return array_values($keywords);
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

    private function markFailed(Transcript $transcript, LegislativeSession $session, ?string $error = null): void
    {
        $transcript->update([
            'status' => 'failed',
            'processing_error' => $error ?: 'Transcription failed.',
            'ended_at' => now(),
        ]);

        event(new TranscriptUpdated($session, $transcript->refresh(), null, 'failed'));
    }
}
