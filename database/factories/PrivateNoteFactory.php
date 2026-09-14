<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\PrivateNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateNote>
 */
class PrivateNoteFactory extends Factory
{
    protected $model = PrivateNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notable_type' => Document::class,
            'notable_id' => Document::factory(),
            'body' => fake()->sentence(14),
            'page_number' => fake()->optional()->numberBetween(1, 20),
            'color' => fake()->optional()->randomElement(['amber', 'sky', 'emerald']),
        ];
    }
}
