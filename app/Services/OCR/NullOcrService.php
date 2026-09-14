<?php

namespace App\Services\OCR;

use App\Contracts\OCR\OcrService;
use App\DTO\OCR\OcrResult;
use Illuminate\Support\Str;
use RuntimeException;

class NullOcrService implements OcrService
{
    public function extractText(string $absolutePath, string $mime): OcrResult
    {
        // Scanned PDFs have no text layer; the null driver must not pretend they do.
        // Returning empty previously surfaced as the opaque "OCR produced no extractable text."
        if (str_starts_with($mime, 'application/pdf')) {
            throw new RuntimeException(
                'Scanned PDF requires OCR. Set OCR_DRIVER=tesseract and install Tesseract plus Poppler (pdftoppm).',
            );
        }

        $basename = basename($absolutePath);
        $stem = pathinfo($basename, PATHINFO_FILENAME);

        $text = implode("\n", [
            '[OCR placeholder — verify against original record]',
            'Source file: '.$basename,
            'Extracted identifier: '.Str::slug($stem, ' '),
            'SECTION 1. General Provisions.',
            'This placeholder text enables ingest pipeline testing without Tesseract.',
            'Ordinance No. '.Str::upper(Str::substr($stem, 0, 8)).' series of '.now()->year.'.',
        ]);

        return new OcrResult(text: $text, pages: 1, engine: 'null');
    }
}
