<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConversation>
 */
class AiConversationFactory extends Factory
{
    protected $model = AiConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement([
                'Summary of the scholarship ordinance',
                'Which committees have overdue referrals?',
                'Compare the two tricycle franchise drafts',
                'Find prior ordinances on watershed protection',
            ]),
            'model' => 'gpt-4o-mini',
            'system_prompt_version' => 'v1',
            'message_count' => 0,
            'last_message_at' => now(),
            'is_archived' => false,
        ];
    }
}
