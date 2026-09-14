<?php

namespace Database\Factories;

use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Motion>
 */
class MotionFactory extends Factory
{
    protected $model = Motion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => LegislativeSession::factory(),
            'type' => fake()->randomElement(['main', 'main', 'amendment', 'subsidiary']),
            'text' => fake()->randomElement([
                'I move that the measure be approved on second reading.',
                'I move that the matter be referred to the Committee on Rules.',
                'I move to amend the measure by inserting a new section on funding.',
                'I move that consideration of the item be deferred to the next session.',
                'I move that the body proceed to the next item on the agenda.',
            ]),
            'status' => 'proposed',
            'moved_by' => User::factory(),
            'moved_at' => now()->subMinutes(fake()->numberBetween(5, 180)),
            'requires_vote' => true,
        ];
    }

    public function seconded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'seconded',
            'seconded_by' => User::factory(),
            'seconded_at' => now()->subMinutes(fake()->numberBetween(1, 5)),
        ]);
    }

    public function carried(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'carried',
            'seconded_by' => User::factory(),
            'seconded_at' => now()->subMinutes(30),
            'disposed_at' => now()->subMinutes(20),
            'voting_round' => 1,
        ]);
    }
}
