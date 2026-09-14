<?php

namespace Database\Factories;

use App\Models\Bookmark;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookmark>
 */
class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bookmarkable_type' => Document::class,
            'bookmarkable_id' => Document::factory(),
            'label' => fake()->optional()->words(3, true),
        ];
    }
}
