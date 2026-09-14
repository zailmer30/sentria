<?php

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Publication;
use App\Services\Documents\DocumentSearchIndexer;
use App\Services\Documents\DocumentTextStore;
use App\Services\Portal\PublicPortalSearchService;
use App\States\Publication\Published;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

it('finds a live publication by a keyword that only appears in the document body', function (): void {
    $document = Document::factory()->published()->create([
        'title' => 'Generic Provincial Measure',
        'abstract' => 'A published measure without the rare keyword in metadata.',
        'reference_number' => 'REF-BODY-001',
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'is_current' => true,
        'ocr_status' => 'completed',
    ]);

    $body = "SECTION 1. Short Title.\nThis ordinance establishes the ZXYQBODYONLYKEYWORD fund for rural clinics.";
    app(DocumentTextStore::class)->put($version, $body);
    app(DocumentSearchIndexer::class)->upsert($version->refresh(), $body);

    Publication::factory()->published()->create([
        'document_id' => $document->getKey(),
        'title' => 'Generic Provincial Measure',
        'summary' => 'Published summary without the rare token.',
        'status' => Published::$name,
    ]);

    $results = app(PublicPortalSearchService::class)->search([
        'keyword' => 'ZXYQBODYONLYKEYWORD',
    ]);

    expect($results->total())->toBeGreaterThan(0)
        ->and(collect($results->items())->pluck('document_id'))->toContain($document->getKey());
});

it('does not return unpublished documents via body keyword search', function (): void {
    $document = Document::factory()->create([
        'title' => 'Draft Hidden Measure',
        'abstract' => 'Not published.',
        'is_public' => false,
        'published_at' => null,
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'is_current' => true,
        'ocr_status' => 'completed',
    ]);

    $body = 'SECTION 1. Contains ZXYQUNPUBLISHEDBODY token in draft only.';
    app(DocumentTextStore::class)->put($version, $body);
    app(DocumentSearchIndexer::class)->upsert($version->refresh(), $body);

    Publication::factory()->create([
        'document_id' => $document->getKey(),
        'title' => 'Draft Hidden Measure',
        'summary' => 'Still in review.',
        'published_at' => null,
        'unpublished_at' => null,
    ]);

    $results = app(PublicPortalSearchService::class)->search([
        'keyword' => 'ZXYQUNPUBLISHEDBODY',
    ]);

    expect(collect($results->items())->pluck('document_id'))->not->toContain($document->getKey());
});
