<?php

namespace App\Services\AI;

use App\Contracts\AI\TranscriptionService;
use App\DTO\AI\TranscriptionResult;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhisperCompatibleTranscriptionService implements TranscriptionService
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $model = null,
        private readonly int $timeout = 120,
        private readonly int $connectTimeout = 5,
        private readonly string $providerName = 'whisper',
    ) {}

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
        unset($mime, $options);

        $apiKey = $this->apiKey ?: config('sentria.transcription.api_key') ?: config('sentria.ai.api_key');
        $baseUrl = rtrim((string) ($this->baseUrl ?: config('sentria.transcription.base_url') ?: config('sentria.ai.base_url', 'https://api.openai.com/v1')), '/');
        $model = (string) ($this->model ?: config('sentria.transcription.model', 'whisper-1'));

        if (! filled($apiKey)) {
            throw new RuntimeException('Transcription API key is not configured.');
        }

        if (! is_readable($absolutePath)) {
            throw new RuntimeException('Audio file is not readable for transcription.');
        }

        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException('Audio file is not readable for transcription.');
        }

        try {
            $payload = [
                'model' => $model,
                'response_format' => 'verbose_json',
                'timestamp_granularities[]' => 'segment',
                'prompt' => $this->prompt($agendaKeywords),
            ];

            if ($languageMode !== 'auto' && $languageMode !== '') {
                $payload['language'] = $languageMode;
            }

            $response = Http::withToken((string) $apiKey)
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->attach('file', $handle, basename($absolutePath))
                ->post("{$baseUrl}/audio/transcriptions", $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('Transcription request failed: '.$exception->getMessage(), 0, $exception);
        }

        $detectedLanguage = TranscriptionLanguage::normalize($response->json('language'));

        /** @var list<array<string, mixed>> $rawSegments */
        $rawSegments = $response->json('segments') ?? [];
        $segments = [];
        $confidences = [];

        foreach ($rawSegments as $index => $segment) {
            $confidence = isset($segment['avg_logprob'])
                ? round(min(1.0, max(0.0, exp((float) $segment['avg_logprob']))), 4)
                : null;

            if ($confidence !== null) {
                $confidences[] = $confidence;
            }

            $segmentLanguage = TranscriptionLanguage::normalize($segment['language'] ?? null) ?? $detectedLanguage;

            $segments[] = [
                'index' => $index,
                'start' => (float) ($segment['start'] ?? 0),
                'end' => (float) ($segment['end'] ?? 0),
                'speaker' => isset($segment['speaker']) ? (string) $segment['speaker'] : null,
                'text' => trim((string) ($segment['text'] ?? '')),
                'confidence' => $confidence,
                'language' => $segmentLanguage,
            ];
        }

        $fullText = trim((string) ($response->json('text') ?? ''));
        $duration = $response->json('duration');

        return new TranscriptionResult(
            fullText: $fullText,
            segments: $segments,
            averageConfidence: $confidences !== [] ? round(array_sum($confidences) / count($confidences), 4) : null,
            durationSeconds: $duration !== null ? (int) ceil((float) $duration) : null,
            language: $detectedLanguage,
            provider: $this->providerName,
            model: $model,
        );
    }

    /**
     * @param  list<string>  $agendaKeywords
     */
    private function prompt(array $agendaKeywords): string
    {
        $path = (string) config('sentria.transcription.prompt_path', resource_path('prompts/transcription_prompt.md'));
        $base = is_readable($path) ? trim((string) file_get_contents($path)) : '';

        $terms = array_values(array_filter($agendaKeywords, fn (mixed $value): bool => is_string($value) && $value !== ''));

        if ($terms === []) {
            return $base;
        }

        return trim($base."\n".implode(', ', $terms));
    }
}
