<?php

namespace App\Services\AI;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Services\Sessions\TranscriptService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AgendaTranscriptSlicer
{
    public function __construct(
        private readonly TranscriptService $transcripts,
    ) {}

    /**
     * Map agenda item ids to usable speech turns for discussion summaries.
     *
     * @return array<string, list<array{text: string, speaker: string|null, attributed: bool, language: string|null}>>
     */
    public function speechByItem(LegislativeSession $session): array
    {
        $chamber = $this->transcripts->chamberForSession($session);

        if ($chamber !== null) {
            return $this->sliceChamber($session, $chamber);
        }

        return $this->sliceTaggedUploads($session);
    }

    /**
     * @return array<string, list<array{text: string, speaker: string|null, attributed: bool, language: string|null}>>
     */
    private function sliceChamber(LegislativeSession $session, Transcript $transcript): array
    {
        $epoch = $session->actual_start_at ?? $session->created_at;

        if (! $epoch instanceof Carbon) {
            return [];
        }

        $windows = $this->itemWindows($session);

        if ($windows === []) {
            return [];
        }

        $byItem = [];

        foreach ($this->usableSegments($transcript) as $segment) {
            $offset = (float) ($segment['start'] ?? 0);
            $window = $this->windowForOffset($windows, $offset);

            if ($window === null) {
                continue;
            }

            $byItem[$window['item_id']][] = $this->turnFromSegment($segment);
        }

        return $byItem;
    }

    /**
     * @return array<string, list<array{text: string, speaker: string|null, attributed: bool, language: string|null}>>
     */
    private function sliceTaggedUploads(LegislativeSession $session): array
    {
        $uploads = Transcript::query()
            ->where('session_id', $session->getKey())
            ->where('source', '!=', 'chamber_channels')
            ->whereNotNull('agenda_item_id')
            ->orderByDesc('created_at')
            ->get();

        $byItem = [];

        foreach ($uploads as $transcript) {
            $itemId = (string) $transcript->agenda_item_id;

            if ($itemId === '' || isset($byItem[$itemId])) {
                continue;
            }

            $turns = [];

            foreach ($this->usableSegments($transcript) as $segment) {
                $turns[] = $this->turnFromSegment($segment);
            }

            if ($turns === [] && is_string($transcript->full_text) && trim($transcript->full_text) !== '') {
                $turns[] = [
                    'text' => trim($transcript->full_text),
                    'speaker' => null,
                    'attributed' => false,
                    'language' => $transcript->language,
                ];
            }

            if ($turns !== []) {
                $byItem[$itemId] = $turns;
            }
        }

        return $byItem;
    }

    /**
     * @return list<array{item_id: string, start: float, end: float, started_at: Carbon}>
     */
    private function itemWindows(LegislativeSession $session): array
    {
        $epoch = $session->actual_start_at ?? $session->created_at;

        if (! $epoch instanceof Carbon) {
            return [];
        }

        $sittingEnd = $session->adjourned_at ?? $session->actual_end_at;
        /** @var Collection<int, AgendaItem> $items */
        $items = $session->agendaItems->values();
        $windows = [];

        foreach ($items as $index => $item) {
            if (! $item->started_at instanceof Carbon) {
                continue;
            }

            $endAt = $item->completed_at;

            if (! $endAt instanceof Carbon) {
                foreach ($items->slice($index + 1) as $candidate) {
                    if ($candidate->started_at instanceof Carbon) {
                        $endAt = $candidate->started_at;
                        break;
                    }
                }
            }

            if (! $endAt instanceof Carbon) {
                $endAt = $sittingEnd;
            }

            if (! $endAt instanceof Carbon) {
                continue;
            }

            $start = (float) ($item->started_at->getTimestamp() - $epoch->getTimestamp());
            $end = (float) ($endAt->getTimestamp() - $epoch->getTimestamp());

            if ($end <= $start) {
                continue;
            }

            $windows[] = [
                'item_id' => (string) $item->getKey(),
                'start' => $start,
                'end' => $end,
                'started_at' => $item->started_at,
            ];
        }

        return $windows;
    }

    /**
     * @param  list<array{item_id: string, start: float, end: float, started_at: Carbon}>  $windows
     * @return array{item_id: string, start: float, end: float, started_at: Carbon}|null
     */
    private function windowForOffset(array $windows, float $offset): ?array
    {
        $matches = array_values(array_filter(
            $windows,
            static fn (array $window): bool => $offset >= $window['start'] && $offset < $window['end'],
        ));

        if ($matches === []) {
            return null;
        }

        usort(
            $matches,
            static fn (array $left, array $right): int => $right['started_at']->getTimestamp() <=> $left['started_at']->getTimestamp(),
        );

        return $matches[0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function usableSegments(Transcript $transcript): array
    {
        /** @var list<array<string, mixed>> $segments */
        $segments = is_array($transcript->segments) ? $transcript->segments : [];
        $usable = [];

        foreach ($this->transcripts->sortSegments($segments) as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '' || $this->isLowConfidence($segment['confidence'] ?? null)) {
                continue;
            }

            $usable[] = $segment;
        }

        return $usable;
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return array{text: string, speaker: string|null, attributed: bool, language: string|null}
     */
    private function turnFromSegment(array $segment): array
    {
        $speaker = isset($segment['speaker']) && is_string($segment['speaker']) && $segment['speaker'] !== ''
            ? $segment['speaker']
            : null;

        return [
            'text' => trim((string) ($segment['text'] ?? '')),
            'speaker' => $speaker,
            'attributed' => array_key_exists('attributed', $segment)
                ? (bool) $segment['attributed']
                : $speaker !== null,
            'language' => isset($segment['language']) && is_string($segment['language']) && $segment['language'] !== ''
                ? $segment['language']
                : null,
        ];
    }

    private function isLowConfidence(mixed $confidence): bool
    {
        if (! is_numeric($confidence)) {
            return false;
        }

        $threshold = (float) config('sentria.transcription.low_confidence', 0.4);

        return (float) $confidence < $threshold;
    }
}
