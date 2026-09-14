<?php

use App\Contracts\AI\TranscriptionService;
use App\DTO\AI\TranscriptionResult;
use App\Services\AI\FailoverTranscriptionService;
use App\Services\AI\TranscriptionServiceFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

function stubTranscription(string $text, string $provider = 'stub'): TranscriptionService
{
    return new class($text, $provider) implements TranscriptionService
    {
        public function __construct(
            private readonly string $text,
            private readonly string $provider,
        ) {}

        public function transcribeSessionAudio(
            string $absolutePath,
            string $mime,
            array $agendaKeywords = [],
            string $languageMode = 'auto',
            array $options = [],
        ): TranscriptionResult {
            unset($absolutePath, $mime, $agendaKeywords, $languageMode, $options);

            return new TranscriptionResult(
                fullText: $this->text,
                segments: [[
                    'index' => 0,
                    'start' => 0.0,
                    'end' => 1.0,
                    'text' => $this->text,
                    'speaker' => null,
                    'confidence' => 0.9,
                    'language' => 'en',
                ]],
                language: 'en',
                provider: $this->provider,
                model: 'stub-model',
            );
        }
    };
}

function explodingTranscription(string $message): TranscriptionService
{
    return new class($message) implements TranscriptionService
    {
        public function __construct(private readonly string $message) {}

        public function transcribeSessionAudio(
            string $absolutePath,
            string $mime,
            array $agendaKeywords = [],
            string $languageMode = 'auto',
            array $options = [],
        ): TranscriptionResult {
            unset($absolutePath, $mime, $agendaKeywords, $languageMode, $options);

            throw new RuntimeException($this->message);
        }
    };
}

it('uses the first provider that succeeds and records that provider', function (): void {
    $service = new FailoverTranscriptionService([
        'qwen3' => explodingTranscription('qwen down'),
        'whisper' => stubTranscription('I second the motion.', 'whisper'),
    ]);

    $result = $service->transcribeSessionAudio('/tmp/unused.wav', 'audio/wav');

    expect($result->fullText)->toBe('I second the motion.')
        ->and($result->provider)->toBe('whisper')
        ->and($result->model)->toBe('stub-model');
});

it('does not call later providers when the first succeeds', function (): void {
    $state = (object) ['called' => []];

    $first = new class($state) implements TranscriptionService
    {
        public function __construct(private readonly object $state) {}

        public function transcribeSessionAudio(
            string $absolutePath,
            string $mime,
            array $agendaKeywords = [],
            string $languageMode = 'auto',
            array $options = [],
        ): TranscriptionResult {
            unset($absolutePath, $mime, $agendaKeywords, $languageMode, $options);
            $this->state->called[] = 'qwen3';

            return new TranscriptionResult(fullText: 'ok', segments: [], provider: 'qwen3', model: 'Qwen/Qwen3-ASR-1.7B');
        }
    };

    $second = new class($state) implements TranscriptionService
    {
        public function __construct(private readonly object $state) {}

        public function transcribeSessionAudio(
            string $absolutePath,
            string $mime,
            array $agendaKeywords = [],
            string $languageMode = 'auto',
            array $options = [],
        ): TranscriptionResult {
            unset($absolutePath, $mime, $agendaKeywords, $languageMode, $options);
            $this->state->called[] = 'whisper';

            return new TranscriptionResult(fullText: 'late', segments: [], provider: 'whisper');
        }
    };

    $result = (new FailoverTranscriptionService([
        'qwen3' => $first,
        'whisper' => $second,
    ]))->transcribeSessionAudio('/tmp/unused.wav', 'audio/wav');

    expect($state->called)->toBe(['qwen3'])
        ->and($result->provider)->toBe('qwen3');
});

it('throws after every provider fails', function (): void {
    $service = new FailoverTranscriptionService([
        'qwen3' => explodingTranscription('local gpu down'),
        'whisper' => explodingTranscription('groq 503'),
    ]);

    $service->transcribeSessionAudio('/tmp/unused.wav', 'audio/wav');
})->throws(RuntimeException::class, 'All transcription providers failed');

it('skips a provider whose circuit is open when a later hop is healthy', function (): void {

    $service = new FailoverTranscriptionService([
        'qwen3' => explodingTranscription('timeout'),
        'whisper' => stubTranscription('fallback text', 'whisper'),
    ], failureThreshold: 1, cooldownSeconds: 60);

    $service->transcribeSessionAudio('/tmp/a.wav', 'audio/wav');

    $state = (object) ['calls' => 0];
    $gated = new class($state) implements TranscriptionService
    {
        public function __construct(private readonly object $state) {}

        public function transcribeSessionAudio(
            string $absolutePath,
            string $mime,
            array $agendaKeywords = [],
            string $languageMode = 'auto',
            array $options = [],
        ): TranscriptionResult {
            unset($absolutePath, $mime, $agendaKeywords, $languageMode, $options);
            $this->state->calls++;

            throw new RuntimeException('should not be called');
        }
    };

    $result = (new FailoverTranscriptionService([
        'qwen3' => $gated,
        'whisper' => stubTranscription('circuit skipped', 'whisper'),
    ], failureThreshold: 1, cooldownSeconds: 60))->transcribeSessionAudio('/tmp/b.wav', 'audio/wav');

    expect($state->calls)->toBe(0)
        ->and($result->fullText)->toBe('circuit skipped')
        ->and($result->provider)->toBe('whisper');
});

it('binds a failover chain from a comma-separated driver', function (): void {
    config([
        'sentria.transcription.driver' => 'qwen3,whisper',
        'sentria.transcription.api_key' => 'gsk-test',
        'sentria.transcription.base_url' => 'https://api.groq.com/openai/v1',
        'sentria.transcription.model' => 'whisper-large-v3-turbo',
        'sentria.transcription.providers.qwen3.base_url' => 'http://qwen.test/v1',
        'sentria.transcription.providers.qwen3.api_key' => 'local',
        'sentria.transcription.providers.qwen3.model' => 'Qwen/Qwen3-ASR-1.7B',
        'sentria.transcription.providers.elevenlabs.api_key' => null,
    ]);

    expect(app(TranscriptionServiceFactory::class)->chain())->toBe(['qwen3', 'whisper'])
        ->and(app(TranscriptionService::class))->toBeInstanceOf(FailoverTranscriptionService::class);
});

it('fails over from Qwen3 to Groq over HTTP', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'wav');
    file_put_contents($path, str_repeat('audio', 32));

    config([
        'sentria.transcription.driver' => 'qwen3,whisper',
        'sentria.transcription.api_key' => 'gsk-groq',
        'sentria.transcription.base_url' => 'https://api.groq.com/openai/v1',
        'sentria.transcription.model' => 'whisper-large-v3-turbo',
        'sentria.transcription.prompt_path' => resource_path('prompts/transcription_prompt.md'),
        'sentria.transcription.providers.qwen3.base_url' => 'http://qwen.test/v1',
        'sentria.transcription.providers.qwen3.api_key' => 'local',
        'sentria.transcription.providers.qwen3.model' => 'Qwen/Qwen3-ASR-1.7B',
        'sentria.transcription.providers.qwen3.timeout' => 5,
        'sentria.transcription.providers.qwen3.connect_timeout' => 1,
    ]);

    Http::fake([
        'http://qwen.test/v1/audio/transcriptions' => Http::response(['error' => 'unavailable'], 503),
        'https://api.groq.com/openai/v1/audio/transcriptions' => Http::response([
            'text' => 'The motion is carried.',
            'language' => 'en',
            'duration' => 1.5,
            'segments' => [
                ['start' => 0.0, 'end' => 1.5, 'text' => 'The motion is carried.', 'avg_logprob' => -0.1],
            ],
        ], 200),
    ]);

    $result = app(TranscriptionService::class)->transcribeSessionAudio($path, 'audio/wav', [], 'auto');

    unlink($path);

    expect($result->fullText)->toBe('The motion is carried.')
        ->and($result->provider)->toBe('whisper')
        ->and($result->model)->toBe('whisper-large-v3-turbo');

    Http::assertSent(fn ($request): bool => $request->url() === 'http://qwen.test/v1/audio/transcriptions');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.groq.com/openai/v1/audio/transcriptions');
});
