<?php

namespace App\Services\AI;

use App\Contracts\AI\EmbeddingService;

/**
 * Deterministic fake embeddings for CI/dev when no API key is configured.
 * Maps each text to a unit vector derived from a hash — stable across runs.
 */
class HashEmbeddingService implements EmbeddingService
{
    public function embed(array $texts): array
    {
        return array_map(fn (string $text): array => $this->vectorForText($text), $texts);
    }

    public function dimensions(): int
    {
        return (int) config('sentria.ai.embedding_dimensions', 1536);
    }

    public function modelName(): string
    {
        return 'hash-local';
    }

    /**
     * @return list<float>
     */
    public function vectorForText(string $text): array
    {
        $dimensions = $this->dimensions();
        $hash = hash('sha256', $text, true);
        $values = [];
        $sumOfSquares = 0.0;

        for ($i = 0; $i < $dimensions; $i++) {
            $byte = ord($hash[$i % strlen($hash)]);
            $value = ($byte / 127.5) - 1.0;
            $values[] = $value;
            $sumOfSquares += $value ** 2;
        }

        $magnitude = sqrt($sumOfSquares) ?: 1.0;

        return array_map(static fn (float $value): float => $value / $magnitude, $values);
    }
}
