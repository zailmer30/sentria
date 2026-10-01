<?php

namespace Database\Factories;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\MinutesCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MinutesCorrection>
 */
class MinutesCorrectionFactory extends Factory
{
    protected $model = MinutesCorrection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => LegislativeSession::factory(),
            'agenda_item_id' => AgendaItem::factory(),
            'as_written' => fake()->sentence(),
            'should_read' => fake()->sentence(),
            'page_number' => fake()->optional()->numberBetween(1, 40),
            'recorded_by' => User::factory(),
        ];
    }

    public function applied(): static
    {
        return $this->state(fn (array $attributes): array => [
            'applied_at' => now(),
            'applied_by' => $attributes['recorded_by'] ?? User::factory(),
        ]);
    }
}
