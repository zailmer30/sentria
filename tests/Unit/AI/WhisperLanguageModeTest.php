<?php

use App\Services\AI\WhisperCompatibleTranscriptionService;
use Illuminate\Support\Facades\Http;

it('does not send a language lock when chamber mode is auto', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'wav');
    file_put_contents($path, str_repeat('audio', 32));

    config([
        'sentria.ai.api_key' => 'sk-chat-unused',
        'sentria.ai.base_url' => 'https://api.openai.com/v1',
        'sentria.transcription.api_key' => 'gsk-test',
        'sentria.transcription.base_url' => 'https://api.example.test/v1',
        'sentria.transcription.model' => 'whisper-large-v3-turbo',
        'sentria.transcription.prompt_path' => resource_path('prompts/transcription_prompt.md'),
    ]);

    Http::fake([
        'https://api.example.test/v1/audio/transcriptions' => Http::response([
            'text' => 'Magandang umaga. I move that we proceed.',
            'language' => 'tl',
            'duration' => 2.5,
            'segments' => [
                [
                    'start' => 0.0,
                    'end' => 2.5,
                    'text' => 'Magandang umaga. I move that we proceed.',
                    'avg_logprob' => -0.2,
                ],
            ],
        ], 200),
    ]);

    $result = app(WhisperCompatibleTranscriptionService::class)->transcribeSessionAudio(
        $path,
        'audio/wav',
        ['Appropriations Ordinance'],
        'auto',
    );

    unlink($path);

    expect($result->language)->toBe('tl')
        ->and($result->segments[0]['language'])->toBe('tl');

    Http::assertSent(function ($request): bool {
        $body = $request->body();

        return $request->url() === 'https://api.example.test/v1/audio/transcriptions'
            && $request->hasHeader('Authorization', 'Bearer gsk-test')
            && str_contains($body, 'whisper-large-v3-turbo')
            && ! str_contains($body, 'name="language"')
            && str_contains($body, 'Appropriations Ordinance');
    });
});

it('posts to Groq without using the chat AI base URL', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'wav');
    file_put_contents($path, str_repeat('audio', 32));

    config([
        'sentria.ai.api_key' => 'sk-openai',
        'sentria.ai.base_url' => 'https://api.openai.com/v1',
        'sentria.transcription.api_key' => 'gsk-groq',
        'sentria.transcription.base_url' => 'https://api.groq.com/openai/v1',
        'sentria.transcription.model' => 'whisper-large-v3-turbo',
        'sentria.transcription.prompt_path' => resource_path('prompts/transcription_prompt.md'),
    ]);

    Http::fake([
        'https://api.groq.com/openai/v1/audio/transcriptions' => Http::response([
            'text' => 'I second the motion.',
            'language' => 'en',
            'duration' => 1.2,
            'segments' => [
                ['start' => 0.0, 'end' => 1.2, 'text' => 'I second the motion.', 'avg_logprob' => -0.1],
            ],
        ], 200),
    ]);

    app(WhisperCompatibleTranscriptionService::class)->transcribeSessionAudio($path, 'audio/wav', [], 'auto');

    unlink($path);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.groq.com/openai/v1/audio/transcriptions'
        && $request->hasHeader('Authorization', 'Bearer gsk-groq'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.openai.com'));
});
