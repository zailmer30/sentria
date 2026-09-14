<?php

namespace Database\Factories;

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subject = fake()->randomElement([
            'establishing a provincial scholarship program for indigent students',
            'regulating the operation of tricycles along provincial roads',
            'appropriating supplemental funds for the provincial health office',
            'declaring a state of calamity in flood-affected municipalities',
            'creating the provincial solid waste management board',
            'adopting the provincial disaster risk reduction and management plan',
            'granting authority to enter into a memorandum of agreement with the DPWH',
            'institutionalizing the provincial anti-drug abuse council',
            'setting fees and charges for the use of provincial sports facilities',
            'prescribing guidelines for the protection of watershed areas',
        ]);

        $type = fake()->randomElement(DocumentType::cases());
        $title = Str::ucfirst("An ordinance {$subject}");

        if (! in_array($type, [DocumentType::ProposedOrdinance, DocumentType::Ordinance], true)) {
            $title = Str::ucfirst("A resolution {$subject}");
        }

        $year = fake()->numberBetween(2023, 2026);
        $sequence = fake()->unique()->numberBetween(1, 9999);

        return [
            'reference_number' => sprintf('%s-%d-%05d', $type->tag(), $year, $sequence),
            'tracking_number' => sprintf('TRK-%s', Str::upper(Str::random(8))),
            'title' => $title,
            'slug' => Str::slug(Str::limit($title, 80, '')).'-'.Str::lower(Str::random(6)),
            'abstract' => fake()->paragraph(3),
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'proposed_effectivity' => 10,
            'explanatory_note' => fake()->paragraph(2),
            'document_type' => $type->value,
            'status' => 'submitted',
            'confidentiality' => Confidentiality::Internal->value,
            'origin' => fake()->randomElement(['member', 'member', 'executive', 'citizen', 'agency']),
            'language' => 'en',
            'author_id' => User::factory(),
            'submitted_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'tags' => fake()->randomElements(
                ['health', 'education', 'infrastructure', 'budget', 'environment', 'peace-and-order'],
                fake()->numberBetween(1, 3),
            ),
            'is_public' => false,
        ];
    }

    public function registered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'registered',
            'registered_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'public-publication',
            'confidentiality' => Confidentiality::Public->value,
            'is_public' => true,
            'published_at' => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }

    public function confidential(): static
    {
        return $this->state(fn (array $attributes): array => [
            'confidentiality' => Confidentiality::Confidential->value,
            'is_public' => false,
        ]);
    }

    public function ofType(DocumentType $type): static
    {
        return $this->state(function (array $attributes) use ($type): array {
            $year = fake()->numberBetween(2023, 2026);
            $sequence = fake()->unique()->numberBetween(1, 9999);

            return [
                'document_type' => $type->value,
                'reference_number' => sprintf('%s-%d-%05d', $type->tag(), $year, $sequence),
            ];
        });
    }
}
