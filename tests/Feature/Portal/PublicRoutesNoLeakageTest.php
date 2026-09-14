<?php

use App\Models\Document;
use App\Models\PrivateNote;
use App\Models\Publication;
use App\Models\User;
use App\States\Publication\Published;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

it('does not expose internal ai or audit fields on public portal pages', function (): void {
    $document = Document::factory()->published()->create([
        'title' => 'Public Transparency Ordinance',
    ]);

    PrivateNote::factory()->create([
        'notable_type' => Document::class,
        'notable_id' => $document->getKey(),
        'body' => 'SECRETARIAT-ONLY-NOTE-XYZ',
    ]);

    $publication = Publication::factory()->published()->create([
        'document_id' => $document->getKey(),
        'title' => 'Public Transparency Ordinance',
        'review_notes' => 'INTERNAL-REVIEW-NOTES-XYZ',
        'status' => Published::$name,
    ]);

    $response = $this->get(route('portal.documents.show', $publication->public_slug));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/DocumentShow')
            ->where('publication.title', 'Public Transparency Ordinance')
            ->missing('publication.review_notes')
            ->missing('publication.ai_draft')
            ->missing('publication.ai_metadata')
            ->missing('publication.private_notes')
        )
        ->assertDontSee('SECRETARIAT-ONLY-NOTE-XYZ', false)
        ->assertDontSee('INTERNAL-REVIEW-NOTES-XYZ', false);
});

it('redirects guests away from internal ai routes', function (): void {
    $this->get('/ai')->assertRedirect('/login');
    $this->get('/ai/conversations/01JTESTCONVERSATION0000000000')->assertRedirect('/login');
});

it('does not expose ai conversation content to unauthorized users', function (): void {
    $this->get('/ai')->assertRedirect('/login');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ai/conversations/01JTESTCONVERSATION0000000000')
        ->assertNotFound();
});
