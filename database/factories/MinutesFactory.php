<?php

namespace Database\Factories;

use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Minutes>
 */
class MinutesFactory extends Factory
{
    protected $model = Minutes::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => LegislativeSession::factory(),
            'status' => 'session-completed',
            'revision' => 1,
            'prepared_by' => User::factory(),
        ];
    }

    /**
     * A generated draft awaiting secretariat review. AI never advances the
     * workflow past this point on its own.
     */
    public function aiDrafted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'ai-draft',
            'ai_draft' => fake()->paragraphs(6, true),
            'ai_generated_at' => now()->subHours(2),
            'ai_model' => 'gpt-4o-mini',
            'ai_metadata' => [
                'source' => 'transcript+official-records',
                'requires_human_verification' => true,
            ],
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'final-minutes',
            'content' => fake()->paragraphs(8, true),
            'approved_by' => User::factory(),
            'approved_at' => now()->subDay(),
            'finalized_at' => now()->subDay(),
        ]);
    }
}
