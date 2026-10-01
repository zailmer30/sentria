<?php

namespace Database\Factories;

use App\Enums\SessionGuestStatus;
use App\Models\LegislativeSession;
use App\Models\SessionGuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionGuest>
 */
class SessionGuestFactory extends Factory
{
    protected $model = SessionGuest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => LegislativeSession::factory(),
            'name' => fake()->name(),
            'organization' => fake()->optional()->company(),
            'speaking_topic' => fake()->optional()->sentence(4),
            'status' => SessionGuestStatus::Invited->value,
        ];
    }
}
