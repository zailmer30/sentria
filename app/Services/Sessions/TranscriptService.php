<?php

namespace App\Services\Sessions;

use App\Events\TranscriptSegmentReceived;
use App\Events\TranscriptUpdated;
use App\Jobs\Sessions\ProcessSessionTranscriptionJob;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\TranscriptSegmentEdit;
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
     * Freeze machine STT fields on a segment. Existing originals are never overwritten.
     *
     * @param  array<string, mixed>  $segment
     * @return array<string, mixed>
     */
    public function stampOriginals(array $segment): array
    {
        if (! array_key_exists('original_text', $segment)) {
            $segment['original_text'] = (string) ($segment['text'] ?? '');
        }

        if (! array_key_exists('original_speaker', $segment)) {
            $segment['original_speaker'] = isset($segment['speaker']) ? (string) $segment['speaker'] : null;
        }

        if (! array_key_exists('original_speaker_id', $segment)) {
            $segment['original_speaker_id'] = isset($segment['speaker_id']) ? (string) $segment['speaker_id'] : null;
        }

        if (! array_key_exists('original_attributed', $segment)) {
            $segment['original_attributed'] = array_key_exists('attributed', $segment)
                ? (bool) $segment['attributed']
                : true;
        }

        return $segment;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    public function stampOriginalsOnMany(array $segments): array
    {
        return array_values(array_map(
            fn (array $segment): array => $this->stampOriginals($segment),
            $segments,
        ));
    }

    public function discardEdits(Transcript $transcript): void
    {
        $transcript->segmentEdits()->delete();
    }

    /**
     * Manually correct a transcript segment. Official records are human-verified.
     * Original STT fields stay frozen; each wording or speaker change is stored separately.
     *
     * @param  array{text?: string, speaker_id?: string|null, gallery?: bool}  $patch
     */
    public function correctSegment(Transcript $transcript, int $segmentIndex, array $patch, User $actor): Transcript
    {
        return DB::transaction(function () use ($transcript, $segmentIndex, $patch, $actor): Transcript {
            $locked = Transcript::query()->lockForUpdate()->find($transcript->getKey());
            abort_unless($locked instanceof Transcript, 404);

            /** @var list<array<string, mixed>> $segments */
            $segments = $locked->segments ?? [];
            $updated = false;
            $oldText = '';
            $oldSpeakerLabel = null;
            $oldSpeakerId = null;
            $textChanged = false;
            $speakerChanged = false;
            $newText = '';
            $newSpeakerLabel = null;
            $newSpeakerId = null;

            foreach ($segments as $offset => $segment) {
                if ((int) ($segment['index'] ?? -1) !== $segmentIndex) {
                    continue;
                }

                $segment = $this->stampOriginals($segment);
                $before = $segment;
                $oldText = (string) ($segment['text'] ?? '');
                $oldSpeakerLabel = isset($segment['speaker']) ? (string) $segment['speaker'] : null;
                $oldSpeakerId = isset($segment['speaker_id']) && is_string($segment['speaker_id']) && $segment['speaker_id'] !== ''
                    ? $segment['speaker_id']
                    : null;

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

                $newText = (string) ($segment['text'] ?? '');
                $newSpeakerLabel = isset($segment['speaker']) ? (string) $segment['speaker'] : null;
                $newSpeakerId = isset($segment['speaker_id']) && is_string($segment['speaker_id']) && $segment['speaker_id'] !== ''
                    ? $segment['speaker_id']
                    : null;

                $textChanged = $newText !== $oldText;
                $speakerChanged = $this->speakerKey($segment) !== $this->speakerKey($before);

                if ($textChanged || $speakerChanged) {
                    $segment['edited_at'] = now()->toIso8601String();
                    $segment['edited_by'] = $actor->getKey();
                }

                $segments[$offset] = $segment;
                $updated = true;
                break;
            }

            abort_unless($updated, 404);

            if ($textChanged) {
                TranscriptSegmentEdit::query()->create([
                    'transcript_id' => $locked->getKey(),
                    'segment_index' => $segmentIndex,
                    'field' => 'text',
                    'old_value' => $oldText,
                    'new_value' => $newText,
                    'user_id' => $actor->getKey(),
                    'created_at' => now(),
                ]);
            }

            if ($speakerChanged) {
                TranscriptSegmentEdit::query()->create([
                    'transcript_id' => $locked->getKey(),
                    'segment_index' => $segmentIndex,
                    'field' => 'speaker',
                    'old_value' => $oldSpeakerLabel,
                    'new_value' => $newSpeakerLabel,
                    'old_speaker_id' => $oldSpeakerId,
                    'new_speaker_id' => $newSpeakerId,
                    'user_id' => $actor->getKey(),
                    'created_at' => now(),
                ]);
            }

            $sorted = $this->sortSegments($segments);
            $fullText = collect($sorted)
                ->pluck('text')
                ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
                ->implode(' ');

            $locked->update([
                'segments' => $segments,
                'full_text' => $fullText,
            ]);

            $session = $locked->session;
            abort_unless($session !== null, 404);

            event(new TranscriptUpdated($session, $locked->refresh(), $this->typedSegments($sorted)));

            return $locked;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function segmentEdits(Transcript $transcript, int $segmentIndex): array
    {
        return $transcript->segmentEdits()
            ->with('user:id,display_name')
            ->where('segment_index', $segmentIndex)
            ->orderBy('created_at')
            ->get()
            ->map(static function (TranscriptSegmentEdit $edit): array {
                $occurred = $edit->created_at;

                return [
                    'id' => $edit->getKey(),
                    'field' => $edit->field,
                    'old_value' => $edit->old_value,
                    'new_value' => $edit->new_value,
                    'old_speaker_id' => $edit->old_speaker_id,
                    'new_speaker_id' => $edit->new_speaker_id,
                    'user_id' => $edit->user_id,
                    'user_name' => $edit->user?->display_name,
                    'created_at' => $occurred?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array{
     *     index: int,
     *     start: float,
     *     text: string,
     *     original_text: string,
     *     speaker: string|null,
     *     original_speaker: string|null,
     *     text_changed: bool,
     *     speaker_changed: bool
     * }>
     */
    public function corrections(array $segments): array
    {
        $rows = [];

        foreach ($this->sortSegments($segments) as $segment) {
            $textChanged = $this->textChanged($segment);
            $speakerChanged = $this->speakerChanged($segment);

            if (! $textChanged && ! $speakerChanged) {
                continue;
            }

            $rows[] = [
                'index' => (int) ($segment['index'] ?? 0),
                'start' => (float) ($segment['start'] ?? 0),
                'text' => (string) ($segment['text'] ?? ''),
                'original_text' => $this->originalText($segment),
                'speaker' => isset($segment['speaker']) ? (string) $segment['speaker'] : null,
                'original_speaker' => $this->originalSpeakerLabel($segment),
                'text_changed' => $textChanged,
                'speaker_changed' => $speakerChanged,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    public function typedSegments(array $segments, bool $includeOriginals = false): array
    {
        return array_values(array_map(
            function (array $segment) use ($includeOriginals): array {
                $payload = [
                    'index' => (int) ($segment['index'] ?? 0),
                    'start' => (float) ($segment['start'] ?? 0),
                    'end' => (float) ($segment['end'] ?? 0),
                    'speaker' => isset($segment['speaker']) ? (string) $segment['speaker'] : null,
                    'speaker_id' => isset($segment['speaker_id']) ? (string) $segment['speaker_id'] : null,
                    'attributed' => array_key_exists('attributed', $segment) ? (bool) $segment['attributed'] : true,
                    'text' => (string) ($segment['text'] ?? ''),
                    'confidence' => isset($segment['confidence']) ? (float) $segment['confidence'] : null,
                    'language' => isset($segment['language']) ? (string) $segment['language'] : null,
                ];

                if (! $includeOriginals) {
                    return $payload;
                }

                $payload['original_text'] = $this->originalText($segment);
                $payload['original_speaker'] = $this->originalSpeakerLabel($segment);
                $payload['original_speaker_id'] = $this->originalSpeakerId($segment);
                $payload['original_attributed'] = $this->originalAttributed($segment);
                $payload['is_edited'] = $this->textChanged($segment) || $this->speakerChanged($segment);
                $payload['edited_at'] = isset($segment['edited_at']) ? (string) $segment['edited_at'] : null;
                $payload['edited_by'] = isset($segment['edited_by']) ? (string) $segment['edited_by'] : null;

                return $payload;
            },
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

            $segment = $this->stampOriginals($segment);
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

    /**
     * @param  array<string, mixed>  $segment
     */
    private function originalText(array $segment): string
    {
        return array_key_exists('original_text', $segment)
            ? (string) $segment['original_text']
            : (string) ($segment['text'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function originalSpeakerLabel(array $segment): ?string
    {
        if (array_key_exists('original_speaker', $segment)) {
            return isset($segment['original_speaker']) ? (string) $segment['original_speaker'] : null;
        }

        return isset($segment['speaker']) ? (string) $segment['speaker'] : null;
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function originalSpeakerId(array $segment): ?string
    {
        if (array_key_exists('original_speaker_id', $segment)) {
            return isset($segment['original_speaker_id']) && $segment['original_speaker_id'] !== ''
                ? (string) $segment['original_speaker_id']
                : null;
        }

        return isset($segment['speaker_id']) && $segment['speaker_id'] !== ''
            ? (string) $segment['speaker_id']
            : null;
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function originalAttributed(array $segment): bool
    {
        if (array_key_exists('original_attributed', $segment)) {
            return (bool) $segment['original_attributed'];
        }

        return array_key_exists('attributed', $segment) ? (bool) $segment['attributed'] : true;
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function textChanged(array $segment): bool
    {
        return (string) ($segment['text'] ?? '') !== $this->originalText($segment);
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function speakerChanged(array $segment): bool
    {
        return $this->speakerKey($segment) !== $this->speakerKey([
            'speaker' => $this->originalSpeakerLabel($segment),
            'speaker_id' => $this->originalSpeakerId($segment),
            'attributed' => $this->originalAttributed($segment),
        ]);
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function speakerKey(array $segment): string
    {
        $attributed = array_key_exists('attributed', $segment) ? (bool) $segment['attributed'] : true;

        if (! $attributed) {
            return 'unattributed';
        }

        $id = isset($segment['speaker_id']) ? (string) $segment['speaker_id'] : '';

        if ($id !== '') {
            return 'member:'.$id;
        }

        return 'label:'.(string) ($segment['speaker'] ?? '');
    }
}
