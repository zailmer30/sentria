<?php

use App\Enums\DocumentType;
use App\Models\Document;
use App\Services\Documents\DocumentReferenceAllocator;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-08-31 12:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('starts a series at 00001 for the current year', function (): void {
    $allocator = app(DocumentReferenceAllocator::class);

    expect($allocator->preview(DocumentType::ProposedOrdinance))->toBe('PO-2026-00001');
});

it('increments from the highest number in the same tag and year', function (): void {
    Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'reference_number' => 'PO-2026-002',
    ]);
    Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'reference_number' => 'PO-2026-001',
    ]);
    Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'reference_number' => 'PR-2026-040',
    ]);
    Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'reference_number' => 'PO-2025-099',
    ]);

    $allocator = app(DocumentReferenceAllocator::class);

    expect($allocator->preview(DocumentType::ProposedOrdinance))->toBe('PO-2026-00003')
        ->and($allocator->preview(DocumentType::ProposedResolution))->toBe('PR-2026-00041');
});

it('does not reuse a number from a soft-deleted document', function (): void {
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'reference_number' => 'PO-2026-001',
    ]);
    $document->delete();

    $allocator = app(DocumentReferenceAllocator::class);

    expect($allocator->allocate(DocumentType::ProposedOrdinance))->toBe('PO-2026-00002');
});
