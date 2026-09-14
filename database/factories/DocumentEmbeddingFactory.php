<?php

namespace Database\Factories;

use App\Enums\Confidentiality;
use App\Models\Document;
use App\Models\DocumentEmbedding;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Pgvector\Laravel\Vector;

/**
 * @extends Factory<DocumentEmbedding>
 */
class DocumentEmbeddingFactory extends Factory
{
    protected $model = DocumentEmbedding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dimensions = (int) config('sentria.ai.embedding_dimensions', 1536);

        return [
            'document_id' => Document::factory(),
            'document_version_id' => DocumentVersion::factory(),
            'chunk_index' => 0,
            'chunk_text' => fake()->paragraph(4),
            'token_count' => fake()->numberBetween(120, 800),
            'page_number' => fake()->numberBetween(1, 20),
            'model' => (string) config('sentria.ai.embedding_model', 'text-embedding-3-small'),
            'confidentiality' => Confidentiality::Internal->value,
            'is_public' => false,
            'embedding' => new Vector(self::randomUnitVector($dimensions)),
        ];
    }

    /**
     * Demo vectors only. Real embeddings come from the configured provider.
     *
     * @return list<float>
     */
    private static function randomUnitVector(int $dimensions): array
    {
        $values = [];
        $sumOfSquares = 0.0;

        for ($i = 0; $i < $dimensions; $i++) {
            $value = mt_rand(-1000, 1000) / 1000;
            $values[] = $value;
            $sumOfSquares += $value ** 2;
        }

        $magnitude = sqrt($sumOfSquares) ?: 1.0;

        return array_map(static fn (float $value): float => $value / $magnitude, $values);
    }
}
