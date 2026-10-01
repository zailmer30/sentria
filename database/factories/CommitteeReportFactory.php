<?php

namespace Database\Factories;

use App\Models\Committee;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitteeReport>
 */
class CommitteeReportFactory extends Factory
{
    protected $model = CommitteeReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'committee_id' => Committee::factory(),
            'subject_document_id' => Document::factory(),
            'report_number' => sprintf('CR-%d-%03d', fake()->numberBetween(2024, 2026), fake()->unique()->numberBetween(1, 999)),
            'recommendation' => fake()->randomElement([
                'approve', 'amend', 'disapprove', 'defer', 'no-action',
            ]),
            'status' => 'draft',
            'findings' => fake()->paragraphs(2, true),
            'recommendation_notes' => fake()->sentence(12),
            'submitted_by' => User::factory(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'submitted',
            'submitted_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function adopted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'adopted',
            'submitted_at' => now()->subDays(20),
            'adopted_at' => now()->subDays(5),
        ]);
    }
}
