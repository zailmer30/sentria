<?php

namespace Database\Factories;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgendaItem>
 */
class AgendaItemFactory extends Factory
{
    protected $model = AgendaItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement([
            'first-reading', 'second-reading', 'third-reading',
            'committee-report', 'unfinished-business', 'new-business',
        ]);

        return [
            'session_id' => LegislativeSession::factory(),
            'position' => fake()->numberBetween(1, 30),
            'item_number' => (string) fake()->numberBetween(1, 30),
            'title' => fake()->sentence(8),
            'description' => fake()->optional()->paragraph(),
            'category' => $category,
            'status' => 'pending',
            'time_allotment_minutes' => fake()->randomElement([5, 10, 15, 30]),
            'requires_vote' => in_array($category, ['second-reading', 'third-reading'], true),
        ];
    }

    public function procedural(string $category, string $title, int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
            'title' => $title,
            'position' => $position,
            'item_number' => (string) $position,
            'requires_vote' => false,
            'time_allotment_minutes' => 5,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);
    }
}
