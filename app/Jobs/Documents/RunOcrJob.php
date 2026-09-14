<?php

namespace App\Jobs\Documents;

use App\Contracts\OCR\OcrService;
use App\Models\DocumentVersion;
use App\Services\Documents\DocumentSearchIndexer;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\DocumentTextStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RunOcrJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $documentVersionId,
    ) {}

    public function handle(
        OcrService $ocr,
        DocumentTextExtractor $textExtractor,
        DocumentTextStore $textStore,
        DocumentSearchIndexer $searchIndexer,
    ): void {
        $version = DocumentVersion::query()->find($this->documentVersionId);

        if ($version === null) {
            return;
        }

        if ($version->ocr_status !== 'pending') {
            $this->ensureExtractedText($version, $textExtractor, $textStore, $searchIndexer, $ocr);

            return;
        }

        $version->update([
            'ocr_status' => 'processing',
            'ocr_error' => null,
        ]);

        try {
            $this->extractAndPersist($version, $ocr, $textExtractor, $textStore, $searchIndexer);
        } catch (Throwable $exception) {
            $version->refresh()->update([
                'ocr_status' => 'failed',
                'ocr_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function extractAndPersist(
        DocumentVersion $version,
        OcrService $ocr,
        DocumentTextExtractor $textExtractor,
        DocumentTextStore $textStore,
        DocumentSearchIndexer $searchIndexer,
    ): void {
        $checksumBefore = $version->checksum_sha256;
        $absolutePath = Storage::disk($version->disk)->path($version->file_path);

        if (! is_readable($absolutePath)) {
            throw new RuntimeException('Document file is not readable for OCR.');
        }

        $mime = (string) $version->mime_type;
        $method = null;
        $pageCount = $version->page_count;
        $text = '';

        if (str_starts_with($mime, 'text/')
            || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            || $mime === 'application/msword') {
            $extracted = $textExtractor->extractWithMethod($absolutePath, $mime);
            $text = trim($extracted['text']);
            $method = $extracted['method'];
            $pageCount = $extracted['page_count'] ?? $pageCount;
        } elseif ($mime === 'application/pdf') {
            $extracted = $textExtractor->extractWithMethod($absolutePath, $mime);
            $pageCount = $extracted['page_count'] ?? $pageCount;

            if ($extracted['text'] !== '' && $extracted['method'] === 'pdftotext') {
                $text = trim($extracted['text']);
                $method = 'pdftotext';
            } else {
                $result = $ocr->extractText($absolutePath, $mime);
                $text = trim($result->text);
                $method = 'ocr';
                $pageCount = $result->pages ?? $pageCount;
            }
        } else {
            $result = $ocr->extractText($absolutePath, $mime);
            $text = trim($result->text);
            $method = 'ocr';
            $pageCount = $result->pages ?? $pageCount;
        }

        if ($text === '') {
            throw new RuntimeException('OCR produced no extractable text.');
        }

        $checksumAfter = hash_file('sha256', $absolutePath);

        if ($checksumAfter !== $checksumBefore) {
            throw new RuntimeException('File checksum changed during OCR — aborting.');
        }

        $textStore->put($version, $text);
        $searchIndexer->upsert($version->refresh(), $text);

        $version->refresh()->update([
            'ocr_status' => 'completed',
            'ocr_error' => null,
            'extraction_method' => $method,
            'page_count' => $pageCount,
        ]);
    }

    private function ensureExtractedText(
        DocumentVersion $version,
        DocumentTextExtractor $textExtractor,
        DocumentTextStore $textStore,
        DocumentSearchIndexer $searchIndexer,
        OcrService $ocr,
    ): void {
        if (trim((string) $textStore->get($version)) !== '') {
            return;
        }

        $version->update([
            'ocr_status' => 'processing',
            'ocr_error' => null,
        ]);

        try {
            $this->extractAndPersist($version->refresh(), $ocr, $textExtractor, $textStore, $searchIndexer);
        } catch (Throwable $exception) {
            $version->refresh()->update([
                'ocr_status' => 'failed',
                'ocr_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
