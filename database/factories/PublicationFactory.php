<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Publication>
 */
class PublicationFactory extends Factory
{
    protected $model = Publication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(9);

        return [
            'document_id' => Document::factory(),
            'status' => 'internal-document',
            'public_slug' => Str::slug(Str::limit($title, 60, '')).'-'.Str::lower(Str::random(6)),
            'title' => $title,
            'summary' => fake()->paragraph(),
            'categories' => fake()->randomElements(['ordinances', 'resolutions', 'notices', 'reports'], 2),
            'redaction_applied' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'published',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subDays(5),
            'published_by' => User::factory(),
            'published_at' => now()->subDays(4),
            'view_count' => fake()->numberBetween(0, 5000),
            'download_count' => fake()->numberBetween(0, 800),
        ])->afterCreating(function (Publication $publication): void {
            $document = $publication->document;

            if ($document !== null) {
                $document->forceFill([
                    'is_public' => true,
                    'published_at' => $publication->published_at ?? now(),
                ])->save();
            }
        });
    }

    public function withRedactions(): static
    {
        return $this->state(fn (array $attributes): array => [
            'redaction_applied' => true,
            'redaction_notes' => 'Personal identifiers of private citizens were removed before publication.',
        ]);
    }
}
