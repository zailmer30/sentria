<?php

namespace App\Services\AI;

use App\Contracts\AI\TranscriptionService;
use App\DTO\AI\TranscriptionResult;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElevenLabsTranscriptionService implements TranscriptionService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.elevenlabs.io',
        private readonly string $model = 'scribe_v2',
        private readonly int $timeout = 120,
        private readonly int $connectTimeout = 5,
        private readonly bool $sendKeyterms = false,
        private readonly bool $diarizeOnUpload = true,
        private readonly bool $audioEventsOnUpload = true,
    ) {}

    /**
     * @param  list<string>  $agendaKeywords
     * @param  array{source?: 'upload'|'chamber'|'chamber_mix'}  $options
     */
    public function transcribeSessionAudio(
        string $absolutePath,
        string $mime,
        array $agendaKeywords = [],
        string $languageMode = 'auto',
        array $options = [],
    ): TranscriptionResult {
        unset($mime);

        if (! is_readable($absolutePath)) {
            throw new RuntimeException('Audio file is not readable for transcription.');
        }

        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException('Audio file is not readable for transcription.');
        }

        $source = $options['source'] ?? 'upload';
        $diarize = $source !== 'chamber' && $this->diarizeOnUpload;
        $tagEvents = $source === 'upload' && $this->audioEventsOnUpload;

        try {
            $payload = [
                'model_id' => $this->model,
                'timestamps_granularity' => 'word',
                'tag_audio_events' => $tagEvents ? 'true' : 'false',
                'diarize' => $diarize ? 'true' : 'false',
                'no_verbatim' => 'false',
            ];

            $language = TranscriptionLanguage::elevenLabsCode($languageMode);

            if ($language !== null) {
                $payload['language_code'] = $language;
            }

            if ($this->sendKeyterms) {
                foreach ($this->keyterms($agendaKeywords) as $index => $term) {
                    $payload["keyterms[{$index}]"] = $term;
                }
            }

            $response = Http::withHeaders(['xi-api-key' => $this->apiKey])
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->attach('file', $handle, basename($absolutePath))
                ->post(rtrim($this->baseUrl, '/').'/v1/speech-to-text', $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('ElevenLabs transcription request failed: '.$exception->getMessage(), 0, $exception);
        }

        $detectedLanguage = TranscriptionLanguage::normalize($response->json('language_code'));
        /** @var list<array<string, mixed>> $words */
        $words = $response->json('words') ?? [];
        $segments = $this->segmentsFromWords($words, $detectedLanguage, $tagEvents, $diarize);
        $fullText = trim((string) ($response->json('text') ?? ''));

        if ($segments === [] && $fullText !== '') {
            $segments[] = [
                'index' => 0,
                'start' => 0.0,
                'end' => (float) ($response->json('audio_duration_secs') ?? 0),
                'speaker' => null,
                'text' => $fullText,
                'confidence' => null,
                'language' => $detectedLanguage,
            ];
        }

        $confidences = array_values(array_filter(array_column($segments, 'confidence'), fn (mixed $value): bool => is_float($value)));
        $duration = $response->json('audio_duration_secs');

        return new TranscriptionResult(
            fullText: $fullText,
            segments: $segments,
            averageConfidence: $confidences !== [] ? round(array_sum($confidences) / count($confidences), 4) : null,
            durationSeconds: $duration !== null ? (int) ceil((float) $duration) : null,
            language: $detectedLanguage,
            provider: 'elevenlabs',
            model: $this->model,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $words
     * @return list<array{index: int, start: float, end: float, speaker: string|null, text: string, confidence: float|null, language: string|null}>
     */
    private function segmentsFromWords(array $words, ?string $language, bool $keepEvents, bool $assignSpeakers): array
    {
        $segments = [];
        $tokens = [];
        $logprobs = [];
        $start = null;
        $end = 0.0;
        $speaker = null;

        $flush = function () use (&$segments, &$tokens, &$logprobs, &$start, &$end, &$speaker, $language): void {
            $text = trim(implode('', $tokens));
            $tokens = [];
            $segmentStart = $start;
            $start = null;
            $segmentLogprobs = $logprobs;
            $logprobs = [];
            $segmentSpeaker = $speaker;
            $speaker = null;

            if ($text === '' || $segmentStart === null) {
                return;
            }

            $confidence = $segmentLogprobs !== []
                ? round(min(1.0, max(0.0, exp(array_sum($segmentLogprobs) / count($segmentLogprobs)))), 4)
                : null;

            $segments[] = [
                'index' => count($segments),
                'start' => $segmentStart,
                'end' => $end,
                'speaker' => $segmentSpeaker,
                'text' => $text,
                'confidence' => $confidence,
                'language' => $language,
            ];
        };

        foreach ($words as $word) {
            $type = (string) ($word['type'] ?? 'word');

            if ($type === 'audio_event' && ! $keepEvents) {
                continue;
            }

            $wordStart = isset($word['start']) ? (float) $word['start'] : null;
            $wordEnd = isset($word['end']) ? (float) $word['end'] : $wordStart;
            $wordSpeaker = $assignSpeakers ? $this->speakerLabel($word['speaker_id'] ?? null) : null;

            if ($start !== null && $wordSpeaker !== null && $speaker !== null && $wordSpeaker !== $speaker) {
                $flush();
            } elseif ($start !== null && $wordStart !== null && ($wordStart - $end) > 0.75) {
                $flush();
            }

            if ($wordSpeaker !== null) {
                $speaker = $wordSpeaker;
            }

            $token = (string) ($word['text'] ?? '');

            if ($type === 'audio_event') {
                $token = $this->eventToken($token);
            }

            if ($type === 'spacing') {
                $tokens[] = $token === '' ? ' ' : $token;
            } elseif ($token !== '') {
                if ($type === 'audio_event' && $tokens !== [] && ! str_ends_with(implode('', $tokens), ' ')) {
                    $tokens[] = ' ';
                }

                $tokens[] = $token;

                if ($type !== 'audio_event' && isset($word['logprob'])) {
                    $logprobs[] = (float) $word['logprob'];
                }
            }

            if ($start === null && $wordStart !== null) {
                $start = $wordStart;
            }

            if ($wordEnd !== null) {
                $end = $wordEnd;
            }
        }

        $flush();

        return $segments;
    }

    private function speakerLabel(mixed $speakerId): ?string
    {
        if (! is_string($speakerId) || $speakerId === '') {
            return null;
        }

        if (preg_match('/^speaker[_-]?(\d+)$/i', $speakerId, $matches) === 1) {
            return 'Speaker '.(int) $matches[1];
        }

        return $speakerId;
    }

    private function eventToken(string $token): string
    {
        $inner = trim($token, " \t[]()");

        if ($inner === '') {
            return '';
        }

        return '['.$inner.']';
    }

    /**
     * @param  list<string>  $agendaKeywords
     * @return list<string>
     */
    private function keyterms(array $agendaKeywords): array
    {
        $terms = [];

        foreach ($agendaKeywords as $keyword) {
            $trimmed = trim($keyword);

            if ($trimmed === '' || strlen($trimmed) > 50) {
                continue;
            }

            $terms[] = $trimmed;
        }

        return array_values(array_unique($terms));
    }
}
