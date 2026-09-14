<?php

namespace Database\Factories;

use App\Enums\VoteChoice;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    protected $model = Vote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => LegislativeSession::factory(),
            'user_id' => User::factory(),
            'voting_round' => 1,
            'choice' => fake()->randomElement([
                VoteChoice::Yes->value,
                VoteChoice::Yes->value,
                VoteChoice::Yes->value,
                VoteChoice::No->value,
                VoteChoice::Abstain->value,
            ]),
            'method' => 'electronic',
            'cast_at' => now(),
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function choice(VoteChoice $choice): static
    {
        return $this->state(fn (array $attributes): array => ['choice' => $choice->value]);
    }
}
