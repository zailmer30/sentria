<?php

use App\Enums\UserRole;
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

it('rejects uploads with double extensions in the original filename', function (): void {
    $secretariat = User::factory()->create([
        'email' => 'upload-security@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    $file = UploadedFile::fake()->createWithContent('report.pdf.exe', '%PDF-1.4 fake');

    $this->actingAs($secretariat)
        ->post(route('documents.store'), [
            'title' => 'Double Extension Test',
            'document_type' => 'proposed-ordinance',
            'confidentiality' => 'internal',
            'reference_number' => 'MO-2026-0099',
            'enacting_clause' => 'Be it ordained by the Sangguniang Bayan, that:',
            'external_author' => 'Maria Santos',
            'explanatory_note' => 'This measure is proposed to address the stated purpose.',
            'file' => $file,
        ])
        ->assertSessionHasErrors();
});
