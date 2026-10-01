<?php

namespace Database\Factories;

use App\Enums\SessionConversationType;
use App\Models\LegislativeSession;
use App\Models\SessionConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionConversation>
 */
class SessionConversationFactory extends Factory
{
    protected $model = SessionConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => LegislativeSession::factory()->inSession(),
            'type' => SessionConversationType::Direct,
            'name' => null,
            'direct_pair_key' => null,
            'created_by' => User::factory(),
            'last_message_at' => null,
        ];
    }

    public function group(?string $name = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SessionConversationType::Group,
            'name' => $name ?? fake()->words(3, true),
            'direct_pair_key' => null,
        ]);
    }
}
