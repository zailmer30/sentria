<?php

namespace App\Services\AI;

use App\Contracts\AI\EmbeddingService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleEmbeddingService implements EmbeddingService
{
    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $payload = [
            'model' => $this->modelName(),
            'input' => $texts,
        ];

        if ($this->shouldSendDimensions()) {
            $payload['dimensions'] = $this->dimensions();
        }

        try {
            $response = Http::withToken($this->embeddingApiKey())
                ->timeout(120)
                ->post($this->embeddingBaseUrl().'/embeddings', $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException($this->embeddingFailureMessage($exception), 0, $exception);
        }

        /** @var list<array{embedding: list<float>, index?: int}> $data */
        $data = $response->json('data') ?? [];

        usort($data, static fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(
            fn (array $row): array => $this->coerceDimensions($row['embedding'] ?? []),
            $data,
        );
    }

    public function dimensions(): int
    {
        return (int) config('sentria.ai.embedding_dimensions', 1536);
    }

    public function modelName(): string
    {
        $configured = (string) config('sentria.ai.embedding_model', 'text-embedding-3-small');

        if ($this->usesGeminiEmbeddings()) {
            return $this->resolveGeminiEmbeddingModel($configured);
        }

        return $configured;
    }

    /**
     * OpenAI `dimensions` is only valid for text-embedding-3-*. Gemini and Groq
     * return HTTP 400 "property 'dimensions' is unsupported" if it is sent.
     */
    private function shouldSendDimensions(): bool
    {
        if ($this->usesGeminiEmbeddings()) {
            return false;
        }

        $configured = config('sentria.ai.embedding_send_dimensions');

        if (is_bool($configured)) {
            return $configured;
        }

        if (is_string($configured) && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return str_starts_with($this->modelName(), 'text-embedding-3-');
    }

    /**
     * Gemini embedding-001 is Matryoshka: default vectors are 3072-d. Truncate
     * and L2-normalize to the pgvector width when the provider cannot accept
     * `dimensions` / `outputDimensionality`.
     *
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function coerceDimensions(array $vector): array
    {
        $expected = $this->dimensions();
        $count = count($vector);

        if ($count === $expected) {
            return $vector;
        }

        if ($count > $expected) {
            return $this->truncateAndNormalize($vector, $expected);
        }

        throw new RuntimeException(
            'Embedding dimension mismatch: expected '.$expected.', got '.$count.'.',
        );
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function truncateAndNormalize(array $vector, int $dimensions): array
    {
        $truncated = array_slice($vector, 0, $dimensions);
        $sumOfSquares = 0.0;

        foreach ($truncated as $value) {
            $sumOfSquares += ((float) $value) ** 2;
        }

        $magnitude = sqrt($sumOfSquares) ?: 1.0;

        return array_map(
            static fn (int|float $value): float => ((float) $value) / $magnitude,
            $truncated,
        );
    }

    private function usesGeminiEmbeddings(): bool
    {
        return str_contains($this->embeddingBaseUrl(), 'generativelanguage.googleapis.com');
    }

    private function resolveGeminiEmbeddingModel(string $configured): string
    {
        $model = str_replace('models/', '', $configured);

        if (str_starts_with($model, 'gemini-')
            || $model === 'text-embedding-004'
            || $model === 'embedding-001') {
            return $model;
        }

        return 'gemini-embedding-001';
    }

    private function embeddingBaseUrl(): string
    {
        $url = config('sentria.ai.embedding_base_url') ?: config('sentria.ai.base_url', 'https://api.openai.com/v1');

        return rtrim((string) $url, '/');
    }

    private function embeddingApiKey(): string
    {
        return (string) (config('sentria.ai.embedding_api_key') ?: config('sentria.ai.api_key'));
    }

    private function embeddingFailureMessage(RequestException $exception): string
    {
        $response = $exception->response;
        $providerMessage = $response?->json('error.message');
        $status = $response?->status();
        $detail = is_string($providerMessage) && $providerMessage !== ''
            ? $providerMessage
            : $exception->getMessage();

        return sprintf(
            'Embedding request failed (%s via %s%s): %s',
            $this->modelName(),
            $this->embeddingBaseUrl(),
            is_int($status) ? ", HTTP {$status}" : '',
            $detail,
        );
    }
}
