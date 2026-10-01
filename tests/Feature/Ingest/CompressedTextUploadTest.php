<?php

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Documents\DocumentTextStore;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
    Queue::fake();
});

it('uploads a pdf with icc-style binary parentheses without query exceptions', function (): void {
    $secretariat = User::factory()->create([
        'email' => 'icc-upload@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    // Minimal PDF-like payload with parenthetical ICC metadata and an invalid UTF-8 byte.
    $binaryBlob = "%PDF-1.4\n1 0 obj<<>>endobj\n"
        .'(en-US sRGB http://www.color.org Creator: HP Manufacturer:IEC Model:sRGB'
        .str_repeat("\xF8\xA9\xB2", 40)
        .")\ntrailer<<>>\n%%EOF\n";

    $file = UploadedFile::fake()->createWithContent('icc-measure.pdf', $binaryBlob, 'application/pdf');

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'ICC Binary Upload Measure',
            'document_type' => DocumentType::ProposedOrdinance->value,
            'confidentiality' => Confidentiality::Internal->value,
            'reference_number' => 'MO-2026-'.fake()->unique()->numerify('####'),
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'external_author' => 'Maria Santos',
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => $file,
        ])
        ->assertRedirect();

    $document = Document::query()->where('title', 'ICC Binary Upload Measure')->firstOrFail();
    $version = DocumentVersion::query()->where('document_id', $document->getKey())->firstOrFail();

    expect($version->ocr_status)->toBe('pending')
        ->and(app(DocumentTextStore::class)->get($version))->toBeNull();
});
