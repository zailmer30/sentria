<?php

namespace Database\Factories;

use App\Models\FloorRecognitionRequest;
use App\Models\LegislativeSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FloorRecognitionRequest>
 */
class FloorRecognitionRequestFactory extends Factory
{
    protected $model = FloorRecognitionRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => LegislativeSession::factory(),
            'user_id' => User::factory(),
            'status' => 'pending',
            'raised_at' => now(),
        ];
    }

    public function recognized(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'recognized',
            'resolved_at' => null,
        ]);
    }
}
