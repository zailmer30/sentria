<?php

use App\Contracts\AI\EmbeddingService;
use App\Services\AI\HashEmbeddingService;
use App\Services\AI\OpenAiCompatibleEmbeddingService;
use Illuminate\Support\Facades\Http;

it('sends dimensions only for OpenAI embedding-3 models', function (): void {
    config([
        'sentria.ai.api_key' => 'sk-test',
        'sentria.ai.embedding_api_key' => 'sk-test',
        'sentria.ai.base_url' => 'https://api.openai.com/v1',
        'sentria.ai.embedding_base_url' => 'https://api.openai.com/v1',
        'sentria.ai.embedding_model' => 'text-embedding-3-small',
        'sentria.ai.embedding_dimensions' => 2,
        'sentria.ai.embedding_send_dimensions' => null,
    ]);

    Http::fake([
        'https://api.openai.com/v1/embeddings' => Http::response([
            'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2]],
            ],
        ], 200),
    ]);

    app(OpenAiCompatibleEmbeddingService::class)->embed(['Hello ordinance']);

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://api.openai.com/v1/embeddings'
            && ($data['model'] ?? null) === 'text-embedding-3-small'
            && ($data['dimensions'] ?? null) === 2;
    });
});

it('omits dimensions for OpenAI-compatible hosts that reject the property', function (): void {
    config([
        'sentria.ai.api_key' => 'gsk-test',
        'sentria.ai.embedding_api_key' => 'gsk-test',
        'sentria.ai.base_url' => 'https://api.groq.com/openai/v1',
        'sentria.ai.embedding_base_url' => 'https://api.groq.com/openai/v1',
        'sentria.ai.embedding_model' => 'nomic-embed-text-v1.5',
        'sentria.ai.embedding_dimensions' => 2,
        'sentria.ai.embedding_send_dimensions' => null,
    ]);

    Http::fake([
        'https://api.groq.com/openai/v1/embeddings' => Http::response([
            'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2]],
            ],
        ], 200),
    ]);

    app(OpenAiCompatibleEmbeddingService::class)->embed(['Hello ordinance']);

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://api.groq.com/openai/v1/embeddings'
            && ! array_key_exists('dimensions', $data);
    });
});

it('uses Gemini embedding-001 without dimensions and truncates Matryoshka vectors', function (): void {
    config([
        'sentria.ai.api_key' => 'gemini-test',
        'sentria.ai.embedding_api_key' => 'gemini-test',
        'sentria.ai.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'sentria.ai.embedding_base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'sentria.ai.embedding_model' => 'text-embedding-3-small',
        'sentria.ai.embedding_dimensions' => 2,
        'sentria.ai.embedding_send_dimensions' => null,
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/openai/embeddings' => Http::response([
            'data' => [
                ['index' => 0, 'embedding' => [3.0, 4.0, 5.0, 6.0]],
            ],
        ], 200),
    ]);

    $service = app(OpenAiCompatibleEmbeddingService::class);
    $vectors = $service->embed(['Section 1. Appropriations.']);

    expect($service->modelName())->toBe('gemini-embedding-001')
        ->and($vectors[0][0])->toEqual(0.6)
        ->and($vectors[0][1])->toEqual(0.8)
        ->and($vectors[0])->toHaveCount(2);

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/openai/embeddings'
            && ($data['model'] ?? null) === 'gemini-embedding-001'
            && ! array_key_exists('dimensions', $data);
    });
});

it('includes the embedding host and provider message on failure', function (): void {
    config([
        'sentria.ai.api_key' => 'sk-test',
        'sentria.ai.embedding_api_key' => 'sk-test',
        'sentria.ai.base_url' => 'https://api.openai.com/v1',
        'sentria.ai.embedding_base_url' => 'https://api.openai.com/v1',
        'sentria.ai.embedding_model' => 'text-embedding-3-small',
        'sentria.ai.embedding_dimensions' => 2,
        'sentria.ai.embedding_send_dimensions' => null,
    ]);

    Http::fake([
        'https://api.openai.com/v1/embeddings' => Http::response([
            'error' => [
                'message' => 'You have no credits remaining.',
                'type' => 'insufficient_quota',
            ],
        ], 429),
    ]);

    expect(fn () => app(OpenAiCompatibleEmbeddingService::class)->embed(['Hello ordinance']))
        ->toThrow(
            RuntimeException::class,
            'Embedding request failed (text-embedding-3-small via https://api.openai.com/v1, HTTP 429): You have no credits remaining.',
        );
});

it('uses local hash embeddings when the model is hash-local even if an API key is set', function (): void {
    config([
        'sentria.ai.api_key' => 'sk-present',
        'sentria.ai.embedding_api_key' => 'sk-present',
        'sentria.ai.embedding_model' => 'hash-local',
    ]);

    expect(app(EmbeddingService::class))->toBeInstanceOf(HashEmbeddingService::class);
});
