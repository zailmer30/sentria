<?php

namespace Database\Factories;

use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitteeReferral>
 */
class CommitteeReferralFactory extends Factory
{
    protected $model = CommitteeReferral::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $referredAt = fake()->dateTimeBetween('-6 months', '-1 week');

        return [
            'document_id' => Document::factory(),
            'committee_id' => Committee::factory(),
            'status' => 'pending',
            'is_primary' => true,
            'instructions' => 'Review, conduct public consultation as needed, and report back to the body.',
            'hearing_waived' => false,
            'referred_at' => $referredAt,
            'due_at' => (clone $referredAt)->modify('+30 days'),
        ];
    }

    public function reported(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'reported',
            'completed_at' => now()->subDays(fake()->numberBetween(1, 20)),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'in-review',
            'due_at' => now()->subDays(fake()->numberBetween(1, 45)),
            'completed_at' => null,
        ]);
    }
}
