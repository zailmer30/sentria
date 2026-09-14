<?php

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\DocumentSubmitted;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
});

function submittedNotifyActor(UserRole $role, string $suffix = 'submit'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('notifies secretariat when a board member submits a document', function (): void {
    Notification::fake();

    $member = submittedNotifyActor(UserRole::BoardMember, 'author');
    $secretariat = submittedNotifyActor(UserRole::Secretariat, 'intake');

    $this->actingAs($member)
        ->post(route('documents.store'), [
            'title' => 'Proposed Ordinance on Drainage',
            'document_type' => DocumentType::ProposedOrdinance->value,
            'confidentiality' => Confidentiality::Internal->value,
            'reference_number' => 'MO-2026-'.fake()->unique()->numerify('####'),
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'proposed_effectivity' => 10,
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => UploadedFile::fake()->createWithContent('drainage.txt', "Section 1\n"),
        ])
        ->assertRedirect();

    Notification::assertSentTo($secretariat, DocumentSubmitted::class);
    Notification::assertNotSentTo($member, DocumentSubmitted::class);
});

it('does not notify the submitting secretariat user about their own upload', function (): void {
    Notification::fake();

    $secretariat = submittedNotifyActor(UserRole::Secretariat, 'self');
    $otherSecretariat = submittedNotifyActor(UserRole::Secretariat, 'peer');

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'Internal Draft Measure',
            'document_type' => DocumentType::ProposedOrdinance->value,
            'confidentiality' => Confidentiality::Internal->value,
            'reference_number' => 'MO-2026-'.fake()->unique()->numerify('####'),
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'proposed_effectivity' => 10,
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => UploadedFile::fake()->createWithContent('draft.txt', "Draft\n"),
        ])
        ->assertRedirect();

    Notification::assertSentTo($otherSecretariat, DocumentSubmitted::class);
    Notification::assertNotSentTo($secretariat, DocumentSubmitted::class);
});
