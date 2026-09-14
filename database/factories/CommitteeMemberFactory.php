<?php

namespace Database\Factories;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitteeMember>
 */
class CommitteeMemberFactory extends Factory
{
    protected $model = CommitteeMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'committee_id' => Committee::factory(),
            'user_id' => User::factory(),
            'position' => 'member',
            'appointed_on' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'is_active' => true,
        ];
    }

    public function chair(): static
    {
        return $this->state(fn (array $attributes): array => ['position' => 'chair']);
    }

    public function viceChair(): static
    {
        return $this->state(fn (array $attributes): array => ['position' => 'vice-chair']);
    }
}
