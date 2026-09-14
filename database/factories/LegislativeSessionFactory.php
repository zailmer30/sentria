<?php

namespace Database\Factories;

use App\Enums\SessionType;
use App\Models\LegislativeSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Number;

/**
 * @extends Factory<LegislativeSession>
 */
class LegislativeSessionFactory extends Factory
{
    protected $model = LegislativeSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', '+2 months');
        $number = fake()->unique()->numberBetween(1, 400);
        $type = fake()->randomElement(SessionType::cases());
        $year = (int) $start->format('Y');

        return [
            'session_number' => sprintf('%s-%d-%05d', $type->tag(), $year, $number),
            'title' => sprintf('%s %s', Number::ordinal($number, 'en'), $type->label()),
            'type' => $type->value,
            'status' => 'draft',
            'legislative_year' => $year,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => (clone $start)->modify('+4 hours'),
            'venue' => 'Session Hall, Provincial Capitol',
            'presiding_officer_id' => null,
            'secretary_id' => null,
            'seated_member_count' => 14,
            'quorum_required' => 8,
            'is_public' => true,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'scheduled']);
    }

    public function inSession(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'in-session',
            'actual_start_at' => now()->subHour(),
            'agenda_locked_at' => now()->subDay(),
            'documents_distributed_at' => now()->subDay(),
        ]);
    }

    public function adjourned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'adjourned',
            'actual_start_at' => now()->subDays(7)->setTime(9, 0),
            'actual_end_at' => now()->subDays(7)->setTime(12, 30),
            'adjourned_at' => now()->subDays(7)->setTime(12, 30),
        ]);
    }

    public function presidedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['presiding_officer_id' => $user->getKey()]);
    }
}
