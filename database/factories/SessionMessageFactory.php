<?php

namespace Database\Factories;

use App\Models\SessionConversation;
use App\Models\SessionMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionMessage>
 */
class SessionMessageFactory extends Factory
{
    protected $model = SessionMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => SessionConversation::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
