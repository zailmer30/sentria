<?php

namespace Database\Factories;

use App\Models\AiCitation;
use App\Models\AiMessage;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCitation>
 */
class AiCitationFactory extends Factory
{
    protected $model = AiCitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_message_id' => AiMessage::factory()->assistant(),
            'document_id' => Document::factory(),
            'rank' => 1,
            'similarity' => fake()->randomFloat(6, 0.7, 0.98),
            'page_number' => fake()->numberBetween(1, 20),
            'quote' => fake()->sentence(20),
        ];
    }
}
