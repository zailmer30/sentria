<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentMetadata>
 */
class DocumentMetadataFactory extends Factory
{
    protected $model = DocumentMetadata::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'key' => fake()->unique()->randomElement([
                'sponsor', 'co-sponsors', 'fiscal-impact', 'implementing-office',
                'related-ordinance', 'public-hearing-date', 'legal-basis',
            ]),
            'value' => fake()->sentence(),
            'source' => 'manual',
        ];
    }

    public function aiExtracted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => 'ai',
            'confidence' => fake()->randomFloat(4, 0.6, 0.99),
        ]);
    }
}
