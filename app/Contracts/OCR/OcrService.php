<?php

namespace App\Contracts\OCR;

use App\DTO\OCR\OcrResult;

interface OcrService
{
    public function extractText(string $absolutePath, string $mime): OcrResult;
}
