<?php

namespace App\Services\AI;

use App\Contracts\AI\TranscriptionService;
use App\DTO\AI\TranscriptionResult;

/**
 * Deterministic speech-to-text for CI and local development.
 * Does not decode audio — seeds output from file hash when present.
 */
class FakeTranscriptionService implements TranscriptionService
{
    /**
     * @param  list<string>  $agendaKeywords
     */
    public function transcribeSessionAudio(
        string $absolutePath,
        string $mime,
        array $agendaKeywords = [],
        string $languageMode = 'auto',
        array $options = [],
    ): TranscriptionResult {
        $source = $options['source'] ?? 'upload';
        $mixer = $source === 'chamber_mix';
        unset($mime);

        $seed = $this->seedFromPath($absolutePath);
        mt_srand($seed);

        $keyword = $agendaKeywords[0] ?? 'agenda item';
        $secondary = $agendaKeywords[1] ?? 'committee report';

        if ($mixer) {
            $templates = [
                ['speaker' => null, 'text' => "Good morning. We proceed to {$keyword} on today's agenda."],
                ['speaker' => null, 'text' => "Secretariat confirms the {$secondary} is distributed to members."],
            ];
            $segmentCount = 2;
        } else {
            $templates = [
                ['speaker' => 'Speaker 1', 'text' => "Good morning. We proceed to {$keyword} on today's agenda."],
                ['speaker' => 'Speaker 2', 'text' => "Secretariat confirms the {$secondary} is distributed to members."],
                ['speaker' => 'Speaker 1', 'text' => "Discussion on {$keyword} may now commence."],
                ['speaker' => 'Speaker 2', 'text' => "Members may raise questions regarding {$keyword} before deliberation."],
                ['speaker' => 'Speaker 1', 'text' => 'The presiding officer notes the motion for further study.'],
                ['speaker' => 'Speaker 2', 'text' => "Reference to {$secondary} is entered into the record."],
            ];
            $segmentCount = 4 + ($seed % 3);
        }
        $segments = [];
        $offset = 0.0;
        $language = $languageMode === 'auto' ? 'und' : $languageMode;

        for ($i = 0; $i < $segmentCount; $i++) {
            $template = $templates[$i % count($templates)];
            $duration = 8.0 + ($i * 2.5);
            $confidence = round(0.82 + (($seed + $i) % 17) / 100, 4);

            $segments[] = [
                'index' => $i,
                'start' => $offset,
                'end' => $offset + $duration,
                'speaker' => $template['speaker'],
                'text' => $template['text'],
                'confidence' => $confidence,
                'language' => $language,
            ];

            $offset += $duration + 0.5;
        }

        $fullText = implode(' ', array_column($segments, 'text'));
        $avgConfidence = round(array_sum(array_column($segments, 'confidence')) / count($segments), 4);

        return new TranscriptionResult(
            fullText: $fullText,
            segments: $segments,
            averageConfidence: $avgConfidence,
            durationSeconds: (int) ceil($offset),
            language: $language,
            provider: 'fake',
            model: 'fake',
        );
    }

    private function seedFromPath(string $absolutePath): int
    {
        if (is_file($absolutePath)) {
            $contents = @file_get_contents($absolutePath);

            if ($contents !== false && $contents !== '') {
                return (int) hexdec(substr(hash('sha256', $contents), 0, 8));
            }

            return (int) hexdec(substr(hash('sha256', $absolutePath), 0, 8));
        }

        return (int) hexdec(substr(hash('sha256', $absolutePath), 0, 8));
    }
}
