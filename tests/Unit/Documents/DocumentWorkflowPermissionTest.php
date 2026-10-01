<?php

use App\Enums\DocumentType;
use App\Models\Document;
use App\States\Document\AgendaInclusion;
use App\States\Document\Approved;
use App\States\Document\CommitteeReferral;
use App\States\Document\CommitteeReport;
use App\States\Document\DocumentWorkflowStatus;
use App\States\Document\FinalDocument;
use App\States\Document\ReadingDeliberation;
use App\States\Document\Registered;
use App\States\Document\Rejected;
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

it('lets a resolution be approved or rejected after second-reading voting', function (): void {
    $document = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => Voting::$name,
        'current_reading' => 2,
    ]);

    expect($document->status->successors())->toBe([Approved::class, Rejected::class]);
});

it('keeps ordinances on final form after second-reading voting', function (): void {
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => Voting::$name,
        'current_reading' => 2,
    ]);

    expect($document->status->successors())->toBe([FinalDocument::class, ReadingDeliberation::class]);
});

it('treats second-reading inclusion as a sitting hop', function (): void {
    expect(DocumentWorkflowStatus::isSessionOwnedHop(AgendaInclusion::class, CommitteeReport::class))->toBeTrue()
        ->and(DocumentWorkflowStatus::isSessionOwnedHop(AgendaInclusion::class, Registered::class))->toBeFalse()
        ->and(DocumentWorkflowStatus::isSessionOwnedHop(ReadingDeliberation::class, AgendaInclusion::class))->toBeTrue()
        ->and(DocumentWorkflowStatus::isSessionOwnedHop(CommitteeReferral::class, ReadingDeliberation::class))->toBeTrue()
        ->and(DocumentWorkflowStatus::isSessionOwnedHop(CommitteeReferral::class, Registered::class))->toBeFalse();
});
