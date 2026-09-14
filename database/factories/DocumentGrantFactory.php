<?php

namespace Database\Factories;

use App\Models\Committee;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentGrant>
 */
class DocumentGrantFactory extends Factory
{
    protected $model = DocumentGrant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'ability' => fake()->randomElement(['view', 'view', 'download', 'comment']),
            'granted_at' => now(),
            'reason' => fake()->sentence(),
        ];
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'role_id' => $role->getKey(),
        ]);
    }

    public function forCommittee(Committee $committee): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'committee_id' => $committee->getKey(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
