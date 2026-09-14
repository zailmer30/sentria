<?php

namespace App\Services\AI;

use App\Contracts\AI\TranscriptionService;
use App\DTO\AI\TranscriptionResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FailoverTranscriptionService implements TranscriptionService
{
    /**
     * @param  array<string, TranscriptionService>  $backends  Ordered name => adapter
     */
    public function __construct(
        private readonly array $backends,
        private readonly int $failureThreshold = 3,
        private readonly int $cooldownSeconds = 60,
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
        if ($this->backends === []) {
            throw new RuntimeException('No transcription providers are configured for failover.');
        }

        $errors = [];
        $names = array_keys($this->backends);
        $attempted = false;

        foreach ($names as $index => $name) {
            if ($this->circuitOpen($name) && $this->hasHealthySuccessor($names, $index)) {
                $errors[] = $name.': circuit open';

                continue;
            }

            $attempted = true;

            try {
                $result = $this->backends[$name]->transcribeSessionAudio(
                    $absolutePath,
                    $mime,
                    $agendaKeywords,
                    $languageMode,
                    $options,
                );
                $this->recordSuccess($name);

                return new TranscriptionResult(
                    fullText: $result->fullText,
                    segments: $result->segments,
                    averageConfidence: $result->averageConfidence,
                    durationSeconds: $result->durationSeconds,
                    language: $result->language,
                    provider: $result->provider ?? $name,
                    model: $result->model,
                );
            } catch (Throwable $exception) {
                $this->recordFailure($name);
                $errors[] = $name.': '.$exception->getMessage();
                Log::warning('Transcription provider failed; trying next.', [
                    'provider' => $name,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (! $attempted) {
            throw new RuntimeException('All transcription provider circuits are open.');
        }

        throw new RuntimeException('All transcription providers failed: '.implode('; ', $errors));
    }

    /**
     * @param  list<string>  $names
     */
    private function hasHealthySuccessor(array $names, int $index): bool
    {
        for ($i = $index + 1; $i < count($names); $i++) {
            if (! $this->circuitOpen($names[$i])) {
                return true;
            }
        }

        return false;
    }

    private function circuitOpen(string $name): bool
    {
        return Cache::has($this->openKey($name));
    }

    private function recordSuccess(string $name): void
    {
        Cache::forget($this->failuresKey($name));
        Cache::forget($this->openKey($name));
    }

    private function recordFailure(string $name): void
    {
        $failures = (int) Cache::increment($this->failuresKey($name));

        if ($failures === 1) {
            Cache::put($this->failuresKey($name), 1, $this->cooldownSeconds);
        }

        if ($failures >= $this->failureThreshold) {
            Cache::put($this->openKey($name), true, $this->cooldownSeconds);
        }
    }

    private function failuresKey(string $name): string
    {
        return "sentria.transcription.circuit.failures.{$name}";
    }

    private function openKey(string $name): string
    {
        return "sentria.transcription.circuit.open.{$name}";
    }
}
