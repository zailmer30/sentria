<?php

namespace Database\Factories;

use App\Models\DocumentAnnotation;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentAnnotation>
 */
class DocumentAnnotationFactory extends Factory
{
    protected $model = DocumentAnnotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_version_id' => DocumentVersion::factory(),
            'payload' => [
                [
                    'annotation' => [
                        'id' => fake()->uuid(),
                        'type' => 8,
                        'pageIndex' => 0,
                    ],
                ],
            ],
        ];
    }
}
