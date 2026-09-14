<?php

namespace App\Services\Documents;

use App\Models\DocumentMetadata;
use App\Models\DocumentVersion;

class DocumentMetadataExtractor
{
    public function __construct(
        private readonly DocumentTextStore $textStore,
    ) {}

    /**
     * @return list<string> keys written
     */
    public function extract(DocumentVersion $version): array
    {
        $text = trim((string) $this->textStore->get($version));

        if ($text === '') {
            return [];
        }

        $written = [];

        if (preg_match('/Ordinance\s+(?:No\.?\s*)?([A-Z0-9\-\/]+)/i', $text, $match) === 1) {
            $this->upsert($version, 'ordinance_number', $match[1], 'ocr');
            $written[] = 'ordinance_number';
        }

        if (preg_match('/Resolution\s+(?:No\.?\s*)?([A-Z0-9\-\/]+)/i', $text, $match) === 1) {
            $this->upsert($version, 'resolution_number', $match[1], 'ocr');
            $written[] = 'resolution_number';
        }

        if (preg_match('/(?:series of|Series)\s+(\d{4})/i', $text, $match) === 1) {
            $this->upsert($version, 'series_year', $match[1], 'ocr');
            $written[] = 'series_year';
        }

        if (preg_match('/\b((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4})\b/i', $text, $match) === 1) {
            $this->upsert($version, 'detected_date', $match[1], 'ocr');
            $written[] = 'detected_date';
        }

        return $written;
    }

    private function upsert(DocumentVersion $version, string $key, string $value, string $source): void
    {
        DocumentMetadata::query()->updateOrCreate(
            [
                'document_id' => $version->document_id,
                'key' => $key,
            ],
            [
                'value' => $value,
                'source' => $source,
                'confidence' => $source === 'ocr' ? 0.75 : null,
            ],
        );
    }
}
