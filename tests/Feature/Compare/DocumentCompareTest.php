<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
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

function compareActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-compare@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function compareDocument(User $author, string $title, string $text): Document
{
    $document = Document::factory()->create([
        'title' => $title,
        'author_id' => $author->getKey(),
        'confidentiality' => Confidentiality::Internal,
    ]);

    $version = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/compare.txt',
        'original_filename' => 'compare.txt',
        'mime_type' => 'text/plain',
        'file_size' => strlen($text),
        'checksum_sha256' => hash('sha256', $text),
        'scan_status' => 'skipped',
        'ocr_status' => 'not_required',
        'text_extracted_at' => now(),
        'processing_status' => 'completed',
        'processed_at' => now(),
        'uploaded_by' => $author->getKey(),
    ]);

    storeExtractedText($version, $text);
    Storage::disk('local')->put($version->file_path, $text);

    return $document->refresh();
}

it('compares two documents and returns structured added removed and changed output', function (): void {
    $member = compareActor(UserRole::BoardMember);

    $left = compareDocument(
        $member,
        'Compare Left Measure',
        "SECTION 1. Short Title.\nAlpha ordinance.\n\nSECTION 2. Appropriations.\nPHP 1,000,000.00 is authorized.",
    );

    $right = compareDocument(
        $member,
        'Compare Right Measure',
        "SECTION 1. Short Title.\nAlpha ordinance revised.\n\nSECTION 2. Appropriations.\nPHP 2,000,000.00 is authorized.\n\nSECTION 3. Effectivity.\nThis ordinance takes effect immediately.",
    );

    $this->actingAs($member)
        ->get(route('ai.compare', [
            'a' => $left->slug,
            'b' => $right->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Ai/Compare')
            ->has('comparison.result.hunks')
            ->has('comparison.result.changed_sections')
            ->has('comparison.result.amount_changes')
            ->where('comparison.result.changed_sections.0.change', 'changed'));
});

it('compares two versions of the same document', function (): void {
    $member = compareActor(UserRole::BoardMember);

    $document = compareDocument(
        $member,
        'Version Compare Measure',
        "SECTION 1. Short Title.\nOriginal text.",
    );

    $secondVersion = DocumentVersion::query()->create([
        'document_id' => $document->getKey(),
        'version_number' => 2,
        'is_current' => true,
        'disk' => 'local',
        'file_path' => 'documents/'.$document->getKey().'/compare-v2.txt',
        'original_filename' => 'compare-v2.txt',
        'mime_type' => 'text/plain',
        'file_size' => 40,
        'checksum_sha256' => hash('sha256', 'updated'),
        'scan_status' => 'skipped',
        'ocr_status' => 'not_required',
        'text_extracted_at' => now(),
        'processing_status' => 'completed',
        'processed_at' => now(),
        'uploaded_by' => $member->getKey(),
    ]);

    DocumentVersion::query()
        ->where('document_id', $document->getKey())
        ->whereKeyNot($secondVersion->getKey())
        ->update(['is_current' => false]);

    $revised = "SECTION 1. Short Title.\nRevised text.";
    storeExtractedText($secondVersion, $revised);
    Storage::disk('local')->put($secondVersion->file_path, $revised);

    $firstVersion = DocumentVersion::query()
        ->where('document_id', $document->getKey())
        ->where('version_number', 1)
        ->firstOrFail();

    $this->actingAs($member)
        ->get(route('documents.versions.compare', [
            'document' => $document,
            'from' => $firstVersion->getKey(),
            'to' => $secondVersion->getKey(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Documents/Compare')
            ->has('comparison.hunks')
            ->has('comparison.changed_sections'));
});

it('forbids comparing documents the user cannot view', function (): void {
    $secretariat = compareActor(UserRole::Secretariat);
    $member = compareActor(UserRole::BoardMember);

    $visible = compareDocument(
        $member,
        'Visible Compare Measure',
        "SECTION 1. Short Title.\nVisible text.",
    );

    $hidden = compareDocument(
        $secretariat,
        'Hidden Compare Measure',
        "SECTION 1. Short Title.\nHidden confidential text.",
    );

    $hidden->update(['confidentiality' => Confidentiality::Confidential->value]);

    $this->actingAs($member)
        ->get(route('ai.compare', [
            'a' => $visible->slug,
            'b' => $hidden->slug,
        ]))
        ->assertForbidden();
});
