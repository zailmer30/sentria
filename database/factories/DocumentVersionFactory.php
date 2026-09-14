<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = Str::slug(fake()->words(4, true)).'.pdf';

        return [
            'document_id' => Document::factory(),
            'version_number' => 1,
            'is_current' => true,
            'disk' => 'local',
            'file_path' => 'documents/'.fake()->uuid().'/'.$filename,
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(40_000, 6_000_000),
            'checksum_sha256' => hash('sha256', fake()->unique()->uuid()),
            'page_count' => fake()->numberBetween(1, 40),
            // Development uses the no-op scanner, which reports `skipped`.
            'scan_status' => 'skipped',
            'scanned_at' => now(),
            'scanner' => 'null',
            'ocr_status' => 'completed',
            'ocr_error' => null,
            'extraction_method' => 'pdftotext',
            'extracted_text_compressed' => null,
            'text_extracted_at' => now(),
            'uploaded_by' => User::factory(),
        ];
    }

    public function superseded(int $versionNumber): static
    {
        return $this->state(fn (array $attributes): array => [
            'version_number' => $versionNumber,
            'is_current' => false,
            'change_summary' => fake()->sentence(),
        ]);
    }

    public function infected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scan_status' => 'infected',
            'scanner' => 'clamav',
            'scan_result' => 'Eicar-Test-Signature',
        ]);
    }
}
