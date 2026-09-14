<?php

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\Document;
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
    config(['sentria.ai.api_key' => null]);
});

function citationActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-ai-cite@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('returns citations with document ids and openable slugs', function (): void {
    $secretariat = citationActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'Citation Test Ordinance',
            'document_type' => DocumentType::ProposedOrdinance->value,
            'confidentiality' => Confidentiality::Internal->value,
            'reference_number' => 'MO-2026-'.fake()->unique()->numerify('####'),
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'proposed_effectivity' => 10,
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => UploadedFile::fake()->createWithContent(
                'citation.txt',
                "SECTION 1. Short Title.\nThis ordinance establishes the Citation Demo Program.\n\nSECTION 2. Appropriations.\nFunds are authorized.",
            ),
        ])
        ->assertRedirect();

    $document = Document::query()->where('title', 'Citation Test Ordinance')->firstOrFail();
    $version = $document->currentVersion;

    ProcessDocumentVersionJob::dispatchSync($version->getKey());

    $response = $this->actingAs($secretariat)
        ->postJson(route('ai.ask'), [
            'question' => 'What does the Citation Demo Program authorize?',
        ])
        ->assertOk();

    $citations = $response->json('message.citations');

    expect($citations)->not->toBeEmpty();

    $first = $citations[0];

    expect($first['document_id'])->toBe($document->getKey())
        ->and($first['document_slug'])->toBe($document->slug)
        ->and($first['url'])->toContain("/documents/{$document->slug}");
});
