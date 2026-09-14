<?php

namespace Database\Factories;

use App\Models\ChamberChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChamberChannel>
 */
class ChamberChannelFactory extends Factory
{
    protected $model = ChamberChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_index' => fake()->unique()->numberBetween(1, 64),
            'user_id' => User::factory()->seatedMember(),
            'label' => null,
            'is_active' => true,
        ];
    }

    public function gallery(int $channelIndex = 99): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel_index' => $channelIndex,
            'user_id' => null,
            'label' => 'Gallery / resource',
            'is_active' => true,
        ]);
    }
}
