<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
use ZipArchive;

class DocumentTextExtractor
{
    public function extract(UploadedFile $file, string $mimeType): string
    {
        return $this->extractFromPath($file->getRealPath() ?: '', $mimeType);
    }

    /**
     * @return array{text: string, method: string|null, page_count: int|null}
     */
    public function extractWithMethod(string $absolutePath, string $mimeType): array
    {
        if ($absolutePath === '' || ! is_readable($absolutePath)) {
            return ['text' => '', 'method' => null, 'page_count' => null];
        }

        if (str_starts_with($mimeType, 'text/')) {
            $contents = file_get_contents($absolutePath);

            return [
                'text' => is_string($contents) ? trim($contents) : '',
                'method' => 'plaintext',
                'page_count' => null,
            ];
        }

        return match ($mimeType) {
            'application/pdf' => $this->extractPdfWithMethod($absolutePath),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [
                'text' => $this->extractDocxText($absolutePath),
                'method' => 'docx',
                'page_count' => null,
            ],
            'application/msword' => [
                'text' => $this->extractDocText($absolutePath),
                'method' => 'docx',
                'page_count' => null,
            ],
            default => ['text' => '', 'method' => null, 'page_count' => null],
        };
    }

    public function extractFromPath(string $absolutePath, string $mimeType): string
    {
        return $this->extractWithMethod($absolutePath, $mimeType)['text'];
    }

    /**
     * @return array{text: string, method: string|null, page_count: int|null}
     */
    private function extractPdfWithMethod(string $path): array
    {
        $pdftotext = $this->runPdftotext($path);

        if ($pdftotext['text'] !== '' && ! $this->isSuspiciouslySparse($pdftotext['text'], $pdftotext['page_count'])) {
            return [
                'text' => $pdftotext['text'],
                'method' => 'pdftotext',
                'page_count' => $pdftotext['page_count'],
            ];
        }

        // Sparse or empty — caller should fall through to OCR.
        return [
            'text' => '',
            'method' => null,
            'page_count' => $pdftotext['page_count'],
        ];
    }

    /**
     * @return array{text: string, page_count: int|null}
     */
    private function runPdftotext(string $path): array
    {
        $binary = (string) config('sentria.documents.pdftotext_bin', '/usr/bin/pdftotext');

        if (! is_executable($binary)) {
            return ['text' => '', 'page_count' => $this->pdfPageCount($path)];
        }

        $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            return ['text' => '', 'page_count' => $this->pdfPageCount($path)];
        }

        // pdftotext emits form-feeds between pages; strip them so empty scans
        // are not mistaken for a non-empty text layer.
        return [
            'text' => trim($process->getOutput(), " \t\n\r\0\x0B\x0C"),
            'page_count' => $this->pdfPageCount($path),
        ];
    }

    private function pdfPageCount(string $path): ?int
    {
        $binary = (string) config('sentria.documents.pdfinfo_bin', '/usr/bin/pdfinfo');

        if (! is_executable($binary)) {
            return null;
        }

        $process = new Process([$binary, $path]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    public function isSuspiciouslySparse(string $text, ?int $pageCount): bool
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return true;
        }

        $alnum = preg_replace('/[^A-Za-z0-9]/u', '', $trimmed) ?? '';
        $pages = max(1, $pageCount ?? 1);
        $threshold = (int) config('sentria.documents.min_chars_per_page', 50);

        return (int) (mb_strlen($alnum) / $pages) < $threshold;
    }

    private function extractDocxText(string $path): string
    {
        if ($path === '' || ! class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        $normalized = str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml);

        return trim(html_entity_decode(strip_tags($normalized)));
    }

    private function extractDocText(string $path): string
    {
        if ($path === '' || ! is_readable($path)) {
            return '';
        }

        $content = file_get_contents($path);

        if (! is_string($content)) {
            return '';
        }

        $text = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $content);

        return trim(preg_replace('/\s+/', ' ', $text ?? '') ?? '');
    }
}
