<?php

use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\Ordinance;
use App\Models\Publication;
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

it('lets anonymous users read a published ordinance on the portal with seo props', function (): void {
    $document = Document::factory()->published()->create([
        'title' => 'Annual Appropriations Ordinance',
        'abstract' => 'Official public appropriations measure for demo LGU.',
    ]);

    $ordinance = Ordinance::factory()->create([
        'document_id' => $document->getKey(),
        'title' => 'Annual Appropriations Ordinance',
        'ordinance_number' => 'O-2026-001',
    ]);

    $publication = Publication::factory()->published()->create([
        'document_id' => $document->getKey(),
        'title' => 'Annual Appropriations Ordinance',
        'summary' => 'Published appropriations ordinance.',
        'status' => Published::$name,
    ]);

    $this->get(route('portal.ordinances.show', $publication->public_slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/DocumentShow')
            ->where('publication.title', 'Annual Appropriations Ordinance')
            ->where('seo.title', 'Annual Appropriations Ordinance')
            ->has('seo.description')
            ->where('seo.type', 'article')
        );

    $this->get(route('portal.documents.show', $publication->public_slug))
        ->assertOk()
        ->assertSee('Annual Appropriations Ordinance', false);
});

it('names the session a published record was acted on', function (): void {
    $session = LegislativeSession::factory()->create(['title' => '42nd Regular Session']);

    $document = Document::factory()->published()->create([
        'title' => 'Provincial Solid Waste Segregation Code',
        'session_id' => $session->getKey(),
    ]);

    Ordinance::factory()->create([
        'document_id' => $document->getKey(),
        'title' => 'Provincial Solid Waste Segregation Code',
        'ordinance_number' => 'O-2026-014',
    ]);

    $publication = Publication::factory()->published()->create([
        'document_id' => $document->getKey(),
        'title' => 'Provincial Solid Waste Segregation Code',
        'status' => Published::$name,
    ]);

    foreach (['portal.documents.show', 'portal.ordinances.show'] as $route) {
        $this->get(route($route, $publication->public_slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/DocumentShow')
                ->where('publication.session', '42nd Regular Session')
            );
    }
});
