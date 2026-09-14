<?php

use App\Services\OCR\NullOcrService;

it('rejects scanned PDFs with an actionable configuration error', function (): void {
    $service = new NullOcrService;

    expect(fn () => $service->extractText('/tmp/scan.pdf', 'application/pdf'))
        ->toThrow(RuntimeException::class, 'Scanned PDF requires OCR');
});

it('still returns placeholder text for image fixtures used in ingest tests', function (): void {
    $service = new NullOcrService;
    $result = $service->extractText('/tmp/scanned-measure.png', 'image/png');

    expect($result->text)->toContain('OCR placeholder')
        ->and($result->engine)->toBe('null');
});
