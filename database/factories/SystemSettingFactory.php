<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(3, true);

        return [
            'key' => Str::slug($label, '.'),
            'group' => fake()->randomElement(['general', 'session', 'documents', 'ai', 'security']),
            'label' => Str::title($label),
            'description' => fake()->sentence(),
            'type' => 'string',
            'value' => fake()->word(),
            'is_public' => false,
            'is_locked' => false,
        ];
    }
}
