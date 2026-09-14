<?php

namespace Database\Factories;

use App\Models\Committee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Committee>
 */
class CommitteeFactory extends Factory
{
    protected $model = Committee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subject = fake()->unique()->randomElement([
            'Rules and Privileges',
            'Appropriations',
            'Ways and Means',
            'Health and Sanitation',
            'Education and Culture',
            'Agriculture and Food',
            'Environment and Natural Resources',
            'Public Works and Infrastructure',
            'Peace and Order',
            'Social Services',
            'Trade, Commerce and Industry',
            'Tourism',
        ]);

        $name = "Committee on {$subject}";

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => Str::upper(Str::substr(Str::slug($subject), 0, 12)),
            'type' => fake()->randomElement(['standing', 'standing', 'standing', 'special', 'ad-hoc']),
            'mandate' => "Reviews and reports on all matters relating to {$subject}.",
            'description' => fake()->paragraph(),
            'established_on' => fake()->dateTimeBetween('-6 years', '-1 year'),
            'is_active' => true,
        ];
    }

    public function dissolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
            'dissolved_on' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }
}
