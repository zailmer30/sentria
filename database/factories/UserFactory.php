<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $middleName = fake()->optional(0.7)->lastName();

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'name_suffix' => fake()->optional(0.1)->randomElement(['Jr.', 'Sr.', 'III']),
            'honorific' => fake()->optional(0.4)->randomElement(['Hon.', 'Atty.', 'Engr.', 'Dr.']),
            'display_name' => "{$firstName} {$lastName}",
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'employee_number' => fake()->optional(0.6)->numerify('EMP-####'),
            'position_title' => fake()->optional(0.8)->jobTitle(),
            'district' => fake()->optional(0.5)->randomElement(['1st District', '2nd District', '3rd District', 'Lone District']),
            'phone' => fake()->optional(0.7)->numerify('+639#########'),
            'locale' => 'en',
            'is_active' => true,
            'is_seated_member' => false,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function seatedMember(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_seated_member' => true,
            'honorific' => 'Hon.',
        ]);
    }
}
