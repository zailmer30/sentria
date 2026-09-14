<?php

use App\Models\Publication;
use App\States\Publication\InternalDocument;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

it('returns 404 not 403 for unpublished portal documents', function (): void {
    $publication = Publication::factory()->create([
        'status' => InternalDocument::$name,
        'published_at' => null,
    ]);

    $this->get(route('portal.documents.show', $publication->public_slug))
        ->assertNotFound();

    $this->get(route('portal.ordinances.show', $publication->public_slug))
        ->assertNotFound();

    $this->get(route('portal.history.show', $publication->public_slug))
        ->assertNotFound();
});

it('returns 404 for withdrawn publications', function (): void {
    $publication = Publication::factory()->published()->create([
        'unpublished_at' => now(),
    ]);

    $this->get(route('portal.documents.show', $publication->public_slug))
        ->assertNotFound();
});
