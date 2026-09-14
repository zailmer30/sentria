<?php

namespace App\Services\OCR;

use App\Contracts\OCR\OcrService;
use App\DTO\OCR\OcrResult;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class TesseractOcrService implements OcrService
{
    public function __construct(
        private readonly NullOcrService $fallback,
        private readonly string $binary,
        private readonly string $pdftoppmBinary = '/usr/bin/pdftoppm',
    ) {}

    public function extractText(string $absolutePath, string $mime): OcrResult
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException('OCR source file is not readable.');
        }

        if (! is_executable($this->binary)) {
            return $this->fallback->extractText($absolutePath, $mime);
        }

        if (str_starts_with($mime, 'application/pdf')) {
            return $this->extractPdf($absolutePath);
        }

        return $this->runTesseract($absolutePath);
    }

    private function extractPdf(string $absolutePath): OcrResult
    {
        if (! is_executable($this->pdftoppmBinary)) {
            throw new RuntimeException(
                'pdftoppm is not executable at '.$this->pdftoppmBinary.'. Install Poppler to OCR scanned PDFs.',
            );
        }

        $tempDir = sys_get_temp_dir().'/sentria-ocr-'.uniqid('', true);
        mkdir($tempDir, 0700, true);

        try {
            $prefix = $tempDir.'/page';
            $convert = new Process([$this->pdftoppmBinary, '-png', $absolutePath, $prefix]);
            $convert->setTimeout(300);
            $convert->run();

            if (! $convert->isSuccessful()) {
                $detail = trim($convert->getErrorOutput() ?: $convert->getOutput());

                throw new RuntimeException(
                    'PDF to image conversion failed (pdftoppm).'.($detail !== '' ? ' '.$detail : ''),
                );
            }

            $pages = glob($prefix.'-*.png') ?: [];
            sort($pages);
            $chunks = [];

            foreach ($pages as $index => $pagePath) {
                $pageResult = $this->runTesseract($pagePath);
                $chunks[] = '--- Page '.($index + 1)." ---\n".$pageResult->text;
            }

            $text = trim(implode("\n\n", $chunks));

            return new OcrResult(
                text: $text,
                pages: count($pages) ?: null,
                engine: 'tesseract',
            );
        } finally {
            array_map('unlink', glob($tempDir.'/*') ?: []);
            @rmdir($tempDir);
        }
    }

    private function runTesseract(string $imagePath): OcrResult
    {
        $process = new Process([$this->binary, $imagePath, 'stdout', '-l', 'eng']);
        $process->setTimeout(300);

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            return $this->fallback->extractText($imagePath, 'image/png');
        }

        return new OcrResult(
            text: trim($process->getOutput()),
            pages: 1,
            engine: 'tesseract',
        );
    }
}
