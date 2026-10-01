<?php

use App\Enums\DocumentType;

it('assigns a unique series tag to every document type', function (): void {
    $tags = array_map(fn (DocumentType $type): string => $type->tag(), DocumentType::cases());

    expect($tags)->toHaveCount(count(array_unique($tags)));
});

it('uses PO for proposed ordinances', function (): void {
    expect(DocumentType::ProposedOrdinance->tag())->toBe('PO')
        ->and(DocumentType::ProposedResolution->tag())->toBe('PR');
});

it('groups ordinance and resolution measures', function (): void {
    expect(DocumentType::Ordinance->isOrdinanceMeasure())->toBeTrue()
        ->and(DocumentType::ProposedOrdinance->isOrdinanceMeasure())->toBeTrue()
        ->and(DocumentType::Resolution->isOrdinanceMeasure())->toBeFalse()
        ->and(DocumentType::Resolution->isResolutionMeasure())->toBeTrue()
        ->and(DocumentType::ProposedResolution->isResolutionMeasure())->toBeTrue()
        ->and(DocumentType::Ordinance->isResolutionMeasure())->toBeFalse()
        ->and(DocumentType::Minutes->isMeasure())->toBeFalse();
});

it('requires third reading only for ordinance measures', function (): void {
    expect(DocumentType::ProposedOrdinance->requiresThirdReading())->toBeTrue()
        ->and(DocumentType::Ordinance->requiresThirdReading())->toBeTrue()
        ->and(DocumentType::ProposedResolution->requiresThirdReading())->toBeFalse()
        ->and(DocumentType::Resolution->requiresThirdReading())->toBeFalse()
        ->and(DocumentType::Minutes->requiresThirdReading())->toBeFalse()
        ->and(DocumentType::ProposedOrdinance->finalReadingNumber())->toBe(3)
        ->and(DocumentType::Ordinance->finalReadingNumber())->toBe(3)
        ->and(DocumentType::ProposedResolution->finalReadingNumber())->toBe(2)
        ->and(DocumentType::Resolution->finalReadingNumber())->toBe(2);
});
