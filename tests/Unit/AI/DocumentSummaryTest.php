<?php

use App\DTO\AI\DocumentSummary;

it('accepts the canonical string and list shape', function (): void {
    $summary = DocumentSummary::fromMetadataJson([
        'executive_summary' => 'A shoreline setback ordinance.',
        'purpose' => 'Limit construction along the coast.',
        'key_provisions' => ['Creates a 20-meter setback'],
        'important_dates' => ['January 1, 2026'],
        'financial_info' => 'PHP 1,000,000 from the General Fund',
        'affected_offices' => ['Provincial Planning Office'],
        'related_docs_hints' => ['Ordinance No. 12'],
        'potential_issues' => ['Survey markers are not defined'],
        'model' => 'gpt-4o-mini',
        'generated_at' => '2026-09-11T01:00:00+00:00',
    ]);

    expect($summary->financialInfo)->toBe('PHP 1,000,000 from the General Fund')
        ->and($summary->keyProvisions)->toBe(['Creates a 20-meter setback']);
});

it('flattens object and array fields returned by chat models', function (): void {
    $summary = DocumentSummary::fromMetadataJson([
        'executive_summary' => ['Limits shoreline construction', 'Applies province-wide'],
        'purpose' => ['Protect coastal barangays'],
        'key_provisions' => 'Creates a 20-meter setback',
        'important_dates' => [['label' => 'Effectivity', 'date' => 'January 1, 2026']],
        'financial_info' => [
            'amount' => 'PHP 1,000,000',
            'source' => 'General Fund',
        ],
        'affected_offices' => null,
        'related_docs_hints' => '',
        'potential_issues' => [['issue' => 'Survey markers are not defined']],
        'model' => 'gpt-4o-mini',
    ]);

    expect($summary->executiveSummary)->toBe('Limits shoreline construction; Applies province-wide')
        ->and($summary->purpose)->toBe('Protect coastal barangays')
        ->and($summary->keyProvisions)->toBe(['Creates a 20-meter setback'])
        ->and($summary->importantDates)->toBe(['label: Effectivity; date: January 1, 2026'])
        ->and($summary->financialInfo)->toBe('amount: PHP 1,000,000; source: General Fund')
        ->and($summary->affectedOffices)->toBe([])
        ->and($summary->relatedDocsHints)->toBe([])
        ->and($summary->potentialIssues)->toBe(['issue: Survey markers are not defined']);
});

it('treats empty financial objects as absent', function (): void {
    $summary = DocumentSummary::fromMetadataJson([
        'financial_info' => [],
    ]);

    expect($summary->financialInfo)->toBeNull();
});
