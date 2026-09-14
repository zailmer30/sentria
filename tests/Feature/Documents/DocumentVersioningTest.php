<?php

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
});

function docActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-docs@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function textUpload(string $name, string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

it('creates version one on initial upload and version two on subsequent upload', function (): void {
    $secretariat = docActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'Test Measure Alpha',
            'document_type' => DocumentType::ProposedOrdinance->value,
            'confidentiality' => Confidentiality::Internal->value,
            'reference_number' => 'MO-2026-'.fake()->unique()->numerify('####'),
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'proposed_effectivity' => 10,
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => textUpload('alpha-v1.txt', "Line one\nLine two"),
        ])
        ->assertRedirect();

    $document = Document::query()->where('title', 'Test Measure Alpha')->firstOrFail();
    expect($document->version_count)->toBe(1);

    /** @var DocumentVersion $v1 */
    $v1 = DocumentVersion::query()->where('document_id', $document->getKey())->where('version_number', 1)->firstOrFail();
    $v1Path = $v1->file_path;
    $v1Checksum = $v1->checksum_sha256;

    $this->actingAs($secretariat)
        ->post(route('documents.versions.store', $document), [
            'file' => textUpload('alpha-v2.txt', "Line one\nLine two revised"),
            'change_summary' => 'Second reading amendments',
        ])
        ->assertRedirect(route('documents.show', $document));

    $document->refresh();
    expect($document->version_count)->toBe(2);

    $v1->refresh();
    expect($v1->file_path)->toBe($v1Path)
        ->and($v1->checksum_sha256)->toBe($v1Checksum)
        ->and($v1->is_current)->toBeFalse();

    /** @var DocumentVersion $v2 */
    $v2 = DocumentVersion::query()->where('document_id', $document->getKey())->where('version_number', 2)->firstOrFail();
    expect($v2->is_current)->toBeTrue()
        ->and($v2->file_path)->not->toBe($v1Path);

    $this->actingAs($secretariat)
        ->get(route('documents.versions.download', [$document, $v1]))
        ->assertOk();

    Storage::disk('local')->assertExists($v1Path);
    Storage::disk('local')->assertExists($v2->file_path);
});
