<?php

namespace App\Services\Sessions;

use App\Events\TranscriptSegmentReceived;
use App\Events\TranscriptUpdated;
use App\Jobs\Sessions\ProcessSessionTranscriptionJob;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use App\States\Session\InSession;
use App\States\Session\Suspended;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptService
{
    /**
     * Create a transcript from uploaded session audio and queue STT processing.
     * Recordings and transcripts are never auto-published.
     */
    public function createFromUpload(
        User $actor,
        LegislativeSession $session,
        UploadedFile $audio,
        ?AgendaItem $agendaItem = null,
    ): Transcript {
        $disk = 'local';
        $path = sprintf(
            'sessions/%s/transcripts/%s.%s',
            $session->getKey(),
            Str::ulid(),
            $audio->getClientOriginalExtension() ?: 'wav',
        );

        Storage::disk($disk)->putFileAs(
            dirname($path),
            $audio,
            basename($path),
        );

        $existing = $this->primaryForSession($session);
        $payload = [
            'agenda_item_id' => $agendaItem?->getKey() ?? $existing?->agenda_item_id,
            'status' => 'pending',
            'processing_error' => null,
            'language' => app()->getLocale(),
            'disk' => $disk,
            'audio_path' => $path,
            'started_at' => now(),
            'ended_at' => null,
            'provider' => null,
            'model' => null,
            'created_by' => $actor->getKey(),
        ];

        if ($existing !== null) {
            $existing->update($payload);
            $transcript = $existing->refresh();
        } else {
            $transcript = Transcript::query()->create([
                'session_id' => $session->getKey(),
                'source' => 'live_stt',
                ...$payload,
            ]);
        }

        ProcessSessionTranscriptionJob::dispatch($transcript->getKey());

        return $transcript;
    }

    /**
     * @return list<array{
     *     index: int,
     *     start: float,
     *     end: float,
     *     speaker: string|null,
     *     text: string,
     *     confidence: float|null,
     *     agenda_item_id: string|null,
     *     agenda_title: string|null,
     *     jump_url: string
     * }>
     */
    public function search(Transcript $transcript, string $query): array
    {
        $needle = Str::lower(trim($query));

        if ($needle === '') {
            return [];
        }

        /** @var list<array<string, mixed>> $segments */
        $segments = $transcript->segments ?? [];
        $agendaTitle = $transcript->agendaItem?->title;
        $results = [];

        foreach ($this->sortSegments($segments) as $segment) {
            $text = (string) ($segment['text'] ?? '');

            if (! Str::contains(Str::lower($text), $needle)) {
                continue;
            }

            $index = (int) ($segment['index'] ?? 0);

            $results[] = [
                'index' => $index,
                'start' => (float) ($segment['start'] ?? 0),
                'end' => (float) ($segment['end'] ?? 0),
                'speaker' => isset($segment['speaker']) ? (string) $segment['speaker'] : null,
                'text' => $text,
                'confidence' => isset($segment['confidence']) ? (float) $segment['confidence'] : null,
                'agenda_item_id' => $transcript->agenda_item_id,
                'agenda_title' => $agendaTitle,
                'jump_url' => route('sessions.transcript.show', [
                    'session' => $transcript->session_id,
                    't' => (int) floor((float) ($segment['start'] ?? 0)),
                ]),
            ];
        }

        return $results;
    }

    /**
     * Manually correct a transcript segment. Official records are human-verified.
     *
     * @param  array{text?: string, speaker_id?: string|null, gallery?: bool}  $patch
     */
    public function correctSegment(Transcript $transcript, int $segmentIndex, array $patch): Transcript
    {
        /** @var list<array<string, mixed>> $segments */
        $segments = $transcript->segments ?? [];
        $updated = false;

        foreach ($segments as &$segment) {
            if ((int) ($segment['index'] ?? -1) !== $segmentIndex) {
                continue;
            }

            if (array_key_exists('text', $patch) && is_string($patch['text'])) {
                $segment['text'] = trim($patch['text']);
            }

            if (($patch['gallery'] ?? false) === true) {
                $segment['attributed'] = true;
                $segment['speaker_id'] = null;
                $segment['speaker'] = __('transcripts.gallery');
            } elseif (array_key_exists('speaker_id', $patch) && is_string($patch['speaker_id']) && $patch['speaker_id'] !== '') {
                $member = User::query()->find($patch['speaker_id']);
                abort_unless($member instanceof User, 422);

                $segment['attributed'] = true;
                $segment['speaker_id'] = $member->getKey();
                $segment['speaker'] = $member->display_name;
            }

            $updated = true;
            break;
        }

        unset($segment);

        abort_unless($updated, 404);

        $sorted = $this->sortSegments($segments);
        $fullText = collect($sorted)
            ->pluck('text')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->implode(' ');

        $transcript->update([
            'segments' => $segments,
            'full_text' => $fullText,
        ]);

        $session = $transcript->session;
        abort_unless($session !== null, 404);

        event(new TranscriptUpdated($session, $transcript->refresh(), $this->typedSegments($sorted)));

        return $transcript;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array{index: int, start: float, end: float, speaker?: string|null, speaker_id?: string|null, attributed?: bool, text: string, confidence?: float|null, language?: string|null}>
     */
    public function typedSegments(array $segments): array
    {
        return array_values(array_map(
            static fn (array $segment): array => [
                'index' => (int) ($segment['index'] ?? 0),
                'start' => (float) ($segment['start'] ?? 0),
                'end' => (float) ($segment['end'] ?? 0),
                'speaker' => isset($segment['speaker']) ? (string) $segment['speaker'] : null,
                'speaker_id' => isset($segment['speaker_id']) ? (string) $segment['speaker_id'] : null,
                'attributed' => array_key_exists('attributed', $segment) ? (bool) $segment['attributed'] : true,
                'text' => (string) ($segment['text'] ?? ''),
                'confidence' => isset($segment['confidence']) ? (float) $segment['confidence'] : null,
                'language' => isset($segment['language']) ? (string) $segment['language'] : null,
            ],
            $segments,
        ));
    }

    /**
     * Unassigned turns plus a short attributed tail for live floor screens.
     *
     * @param  list<array<string, mixed>>  $segments
     * @return list<array{index: int, start: float, end: float, speaker?: string|null, speaker_id?: string|null, attributed?: bool, text: string, confidence?: float|null, language?: string|null}>
     */
    public function floorLiveSegments(array $segments, int $attributedTail = 12): array
    {
        $typed = $this->typedSegments($this->sortSegments($segments));
        $unassigned = array_values(array_filter(
            $typed,
            static fn (array $segment): bool => ($segment['attributed'] ?? true) === false,
        ));
        $attributed = array_values(array_filter(
            $typed,
            static fn (array $segment): bool => ($segment['attributed'] ?? true) !== false,
        ));
        $recent = array_slice($attributed, -max(1, $attributedTail));

        return $this->typedSegments($this->sortSegments([...$unassigned, ...$recent]));
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    public function sortSegments(array $segments): array
    {
        usort($segments, static function (array $left, array $right): int {
            $start = ((float) ($left['start'] ?? 0)) <=> ((float) ($right['start'] ?? 0));

            if ($start !== 0) {
                return $start;
            }

            return ((int) ($left['index'] ?? 0)) <=> ((int) ($right['index'] ?? 0));
        });

        return $segments;
    }

    /**
     * Append a live segment during streaming STT. Does not infer vote outcomes.
     *
     * @param  array{index: int, start: float, end: float, speaker?: string|null, speaker_id?: string|null, attributed?: bool, text: string, confidence?: float|null, language?: string|null}  $segment
     */
    public function appendLiveSegment(Transcript $transcript, array $segment): Transcript
    {
        return DB::transaction(function () use ($transcript, $segment): Transcript {
            $locked = Transcript::query()->lockForUpdate()->find($transcript->getKey());
            abort_unless($locked instanceof Transcript, 404);

            /** @var list<array<string, mixed>> $segments */
            $segments = $locked->segments ?? [];

            $index = (int) ($segment['index'] ?? -1);

            foreach ($segments as $existing) {
                if ((int) ($existing['index'] ?? -1) === $index) {
                    return $locked;
                }
            }

            $segments[] = $segment;

            $sorted = $this->sortSegments($segments);
            $fullText = collect($sorted)
                ->pluck('text')
                ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
                ->implode(' ');

            $locked->update([
                'segments' => $segments,
                'full_text' => $fullText,
                'status' => 'processing',
            ]);

            $session = $locked->session;
            abort_unless($session !== null, 404);

            $locked->refresh();
            event(new TranscriptSegmentReceived($session, $locked, $this->typedSegments([$segment])[0]));

            return $locked;
        });
    }

    public function chamberForSession(LegislativeSession $session): ?Transcript
    {
        return Transcript::query()
            ->where('session_id', $session->getKey())
            ->where('source', 'chamber_channels')
            ->latest('created_at')
            ->first();
    }

    public function primaryForSession(LegislativeSession $session): ?Transcript
    {
        $live = $session->status instanceof InSession || $session->status instanceof Suspended;

        if ($live) {
            $chamber = $this->chamberForSession($session);

            if ($chamber !== null) {
                return $chamber;
            }
        }

        return Transcript::query()
            ->where('session_id', $session->getKey())
            ->latest('created_at')
            ->first();
    }
}
