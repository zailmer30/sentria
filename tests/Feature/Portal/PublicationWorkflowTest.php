<?php

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Publication;
use App\Models\User;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Publication\InternalDocument;
use App\States\Publication\MarkPublic;
use App\States\Publication\PublicationReview;
use App\States\Publication\Published;
use App\States\Publication\SecretariatReview;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

it('publishes a document through the workflow and exposes it on the portal', function (): void {
    $secretariat = User::factory()->create([
        'email' => 'secretariat-portal@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    $document = Document::factory()->create([
        'title' => 'Portal Workflow Ordinance',
        'is_public' => false,
        'published_at' => null,
    ]);

    $publication = Publication::factory()->create([
        'document_id' => $document->getKey(),
        'title' => 'Portal Workflow Ordinance',
        'status' => InternalDocument::$name,
        'published_at' => null,
    ]);

    $transitions = app(GuardedStateTransition::class);

    $transitions->transition($publication, SecretariatReview::class, $secretariat);
    $transitions->transition($publication->fresh(), PublicationReview::class, $secretariat);
    $transitions->transition($publication->fresh(), MarkPublic::class, $secretariat);
    $transitions->transition($publication->fresh(), Published::class, $secretariat);

    $publication->refresh();
    $document->refresh();

    expect($publication->status)->toBeInstanceOf(Published::class)
        ->and($document->is_public)->toBeTrue()
        ->and($document->published_at)->not->toBeNull()
        ->and($publication->published_at)->not->toBeNull();

    $this->get(route('portal.documents.show', $publication->public_slug))
        ->assertOk()
        ->assertSee('Portal Workflow Ordinance', false);
});
