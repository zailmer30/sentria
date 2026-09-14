<?php

use App\Enums\DocumentType;
use App\Models\Document;
use App\States\Document\AgendaInclusion;
use App\States\Document\DocumentWorkflowStatus;
use App\States\Document\ReadingDeliberation;
use App\States\Document\Voting;

it('lets the clerk or the chair open first reading from the calendar', function (): void {
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
    ]);

    expect(DocumentWorkflowStatus::permissionsFor(
        ReadingDeliberation::class,
        AgendaInclusion::class,
        $document,
    ))->toBe(['sessions.start', 'documents.refer']);
});

it('keeps second reading a presiding act', function (): void {
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 2,
    ]);

    expect(DocumentWorkflowStatus::permissionsFor(
        ReadingDeliberation::class,
        AgendaInclusion::class,
        $document,
    ))->toBe(['sessions.start']);
});

it('does not widen return-to-second-reading from a failed vote', function (): void {
    expect(DocumentWorkflowStatus::permissionsFor(
        ReadingDeliberation::class,
        Voting::class,
        null,
    ))->toBe(['sessions.start']);
});
