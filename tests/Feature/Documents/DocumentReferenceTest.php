<?php

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
    CarbonImmutable::setTestNow('2026-08-31 12:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function referenceNumberActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-ref@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('previews the next tagged reference on the submit dialog', function (): void {
    $secretariat = referenceNumberActor(UserRole::Secretariat);

    Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'reference_number' => 'PO-2026-002',
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Index')
            ->has('documentTypes.0', fn (Assert $type) => $type
                ->where('value', DocumentType::ProposedOrdinance->value)
                ->where('tag', 'PO')
                ->where('next_reference', 'PO-2026-00003')
                ->etc()));
});

it('opens the submit dialog from the create route', function (): void {
    $secretariat = referenceNumberActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->get(route('documents.create'))
        ->assertRedirect(route('documents.index', ['submit' => 1]));
});

it('opens the submit dialog when the register is requested with submit=1', function (): void {
    $secretariat = referenceNumberActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->get(route('documents.index', ['submit' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Index')
            ->where('openSubmit', true));
});

it('assigns the next tagged reference when a document is filed', function (): void {
    $secretariat = referenceNumberActor(UserRole::Secretariat);

    Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'reference_number' => 'PO-2026-002',
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'An ordinance establishing a municipal nursery',
            'document_type' => DocumentType::ProposedOrdinance->value,
            'confidentiality' => Confidentiality::Internal->value,
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'external_author' => 'Maria Santos',
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => UploadedFile::fake()->createWithContent('nursery.txt', "Section 1\n"),
        ])
        ->assertRedirect();

    $document = Document::query()->where('title', 'An ordinance establishing a municipal nursery')->firstOrFail();

    expect($document->reference_number)->toBe('PO-2026-00003')
        ->and($document->external_author)->toBe('Maria Santos')
        ->and($document->author_id)->toBe($secretariat->getKey());
});

it('keeps proposed resolutions on their own yearly series', function (): void {
    $secretariat = referenceNumberActor(UserRole::Secretariat);

    Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'reference_number' => 'PO-2026-014',
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'A resolution adopting the annual investment plan',
            'document_type' => DocumentType::ProposedResolution->value,
            'confidentiality' => Confidentiality::Internal->value,
            'enacting_clause' => 'Be it resolved by the Sangguniang Bayan, that:',
            'external_author' => 'Maria Santos',
            'file' => UploadedFile::fake()->createWithContent('aip.txt', "Resolved\n"),
        ])
        ->assertRedirect();

    $document = Document::query()->where('title', 'A resolution adopting the annual investment plan')->firstOrFail();

    expect($document->reference_number)->toBe('PR-2026-00001');
});
