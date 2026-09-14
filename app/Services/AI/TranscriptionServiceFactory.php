<?php

namespace App\Services\AI;

use App\Contracts\AI\TranscriptionService;

class TranscriptionServiceFactory
{
    public function make(): TranscriptionService
    {
        $chain = $this->chain();

        if ($chain === []) {
            return new FakeTranscriptionService;
        }

        $backends = [];

        foreach ($chain as $name) {
            $service = $this->makeNamed($name);

            if ($service === null) {
                continue;
            }

            $backends[$name] = $service;
        }

        if ($backends === []) {
            return new FakeTranscriptionService;
        }

        if (count($backends) === 1) {
            return array_values($backends)[0];
        }

        return new FailoverTranscriptionService(
            $backends,
            (int) config('sentria.transcription.failover_failures', 3),
            (int) config('sentria.transcription.failover_cooldown_seconds', 60),
        );
    }

    /**
     * @return list<string>
     */
    public function chain(): array
    {
        $driver = strtolower(trim((string) config('sentria.transcription.driver', 'null')));

        if ($driver === '' || in_array($driver, ['null', 'fake'], true)) {
            return [];
        }

        $raw = $driver === 'failover'
            ? (string) config('sentria.transcription.failover', '')
            : $driver;

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $names = [];

        foreach ($parts as $part) {
            $canonical = $this->canonicalize($part);

            if ($canonical === '' || $canonical === 'failover' || in_array($canonical, $names, true)) {
                continue;
            }

            $names[] = $canonical;
        }

        return $names;
    }

    public function makeNamed(string $name): ?TranscriptionService
    {
        $name = $this->canonicalize($name);

        return match ($name) {
            'qwen3', 'whisper' => $this->makeWhisperCompatible($name),
            'elevenlabs' => $this->makeElevenLabs(),
            'fake' => new FakeTranscriptionService,
            default => null,
        };
    }

    public function canonicalize(string $name): string
    {
        return match (strtolower(trim($name))) {
            'openai', 'groq', 'whisper-compatible' => 'whisper',
            'qwen', 'qwen3-asr' => 'qwen3',
            'scribe', 'scribe_v2', 'scribe-v2' => 'elevenlabs',
            default => strtolower(trim($name)),
        };
    }

    private function makeWhisperCompatible(string $name): ?WhisperCompatibleTranscriptionService
    {
        $provider = $this->providerConfig($name);

        if ($name === 'whisper') {
            $apiKey = $this->string(config('sentria.transcription.api_key') ?: config('sentria.ai.api_key'));
            $baseUrl = $this->string(config('sentria.transcription.base_url') ?: config('sentria.ai.base_url', 'https://api.openai.com/v1'));
            $model = $this->string(config('sentria.transcription.model', 'whisper-1')) ?: 'whisper-1';
            $timeout = (int) config('sentria.transcription.timeout', 120);
            $connectTimeout = (int) config('sentria.transcription.connect_timeout', 5);
        } else {
            $apiKey = $this->string($provider['api_key'] ?? null);
            $baseUrl = $this->string($provider['base_url'] ?? null);
            $model = $this->string($provider['model'] ?? null) ?: 'whisper-1';
            $timeout = (int) ($provider['timeout'] ?? 15);
            $connectTimeout = (int) ($provider['connect_timeout'] ?? 2);

            if ($name === 'qwen3' && $apiKey === '') {
                $apiKey = 'local';
            }
        }

        if ($apiKey === '' || $baseUrl === '') {
            return null;
        }

        return new WhisperCompatibleTranscriptionService(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            model: $model,
            timeout: $timeout,
            connectTimeout: $connectTimeout,
            providerName: $name,
        );
    }

    private function makeElevenLabs(): ?ElevenLabsTranscriptionService
    {
        $provider = $this->providerConfig('elevenlabs');
        $apiKey = $this->string($provider['api_key'] ?? null);
        $baseUrl = $this->string($provider['base_url'] ?? 'https://api.elevenlabs.io') ?: 'https://api.elevenlabs.io';

        if ($apiKey === '') {
            return null;
        }

        return new ElevenLabsTranscriptionService(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            model: $this->string($provider['model'] ?? 'scribe_v2') ?: 'scribe_v2',
            timeout: (int) ($provider['timeout'] ?? 120),
            connectTimeout: (int) ($provider['connect_timeout'] ?? 5),
            sendKeyterms: (bool) ($provider['keyterms'] ?? false),
            diarizeOnUpload: (bool) ($provider['diarize_upload'] ?? true),
            audioEventsOnUpload: (bool) ($provider['audio_events_upload'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function providerConfig(string $name): array
    {
        $config = config("sentria.transcription.providers.{$name}", []);

        return is_array($config) ? $config : [];
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
