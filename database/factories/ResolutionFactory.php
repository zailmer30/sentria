<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Resolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resolution>
 */
class ResolutionFactory extends Factory
{
    protected $model = Resolution::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2023, 2026);
        $adopted = fake()->dateTimeBetween("{$year}-01-01", "{$year}-12-31");

        return [
            'document_id' => Document::factory()->ofType(DocumentType::Resolution)->published(),
            'resolution_number' => sprintf('RES-%d-%03d', $year, fake()->unique()->numberBetween(1, 999)),
            'series_year' => $year,
            'title' => fake()->sentence(10),
            'purpose' => fake()->paragraph(),
            'category' => fake()->randomElement(['commendation', 'authorization', 'request', 'policy', 'appropriation']),
            'status' => 'adopted',
            'adopted_on' => $adopted,
            'effectivity_date' => $adopted,
        ];
    }
}
