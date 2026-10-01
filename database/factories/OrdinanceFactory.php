<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Ordinance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ordinance>
 */
class OrdinanceFactory extends Factory
{
    protected $model = Ordinance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2023, 2026);
        $enacted = fake()->dateTimeBetween("{$year}-01-01", "{$year}-12-31");

        return [
            'document_id' => Document::factory()->ofType(DocumentType::Ordinance)->published(),
            'ordinance_number' => sprintf('%03d', fake()->unique()->numberBetween(1, 999)),
            'series_year' => $year,
            'title' => fake()->sentence(10),
            'purpose' => fake()->paragraph(),
            'status' => 'enacted',
            'enacted_on' => $enacted,
            'approving_authority' => 'Provincial Governor',
            'effectivity_date' => (clone $enacted)->modify('+25 days'),
        ];
    }

    public function vetoed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'vetoed',
            'effectivity_date' => null,
        ]);
    }
}
