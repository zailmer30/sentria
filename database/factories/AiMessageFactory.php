<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'role' => 'user',
            'content' => fake()->sentence(12),
            'model' => 'gpt-4o-mini',
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'assistant',
            'content' => fake()->paragraphs(2, true),
            'input_tokens' => fake()->numberBetween(200, 4000),
            'output_tokens' => fake()->numberBetween(50, 900),
            'latency_ms' => fake()->numberBetween(300, 6000),
            'finish_reason' => 'stop',
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_flagged' => true,
            'flag_reason' => 'Retrieved document content contained instruction-like text.',
        ]);
    }
}
