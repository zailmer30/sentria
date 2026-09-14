<?php

namespace App\Services\Documents;

use App\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentTextStore
{
    public function put(DocumentVersion $version, string $text): void
    {
        $sanitized = $this->sanitizeUtf8($text);

        if (trim($sanitized) === '') {
            DB::update(
                'update document_versions set extracted_text_compressed = null, text_extracted_at = null, updated_at = ? where id = ?',
                [now(), $version->getKey()],
            );
            $version->refresh();

            return;
        }

        $compressed = gzcompress($sanitized, 9);

        if ($compressed === false) {
            throw new RuntimeException('Failed to compress extracted document text.');
        }

        // Hex-encode so null bytes inside gzip output survive the bind.
        DB::update(
            'update document_versions set extracted_text_compressed = decode(?, \'hex\'), text_extracted_at = ?, updated_at = ? where id = ?',
            [bin2hex($compressed), now(), now(), $version->getKey()],
        );

        $version->refresh();
    }

    public function get(DocumentVersion $version): ?string
    {
        $row = DB::selectOne(
            'select encode(extracted_text_compressed, \'hex\') as hex from document_versions where id = ?',
            [$version->getKey()],
        );

        if ($row === null || $row->hex === null || $row->hex === '') {
            return null;
        }

        $compressed = hex2bin((string) $row->hex);

        if ($compressed === false || $compressed === '') {
            return null;
        }

        $text = @gzuncompress($compressed);

        if ($text === false) {
            throw new RuntimeException('Failed to decompress extracted document text.');
        }

        return $text;
    }

    public function sanitizeUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $cleaned = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        if (! is_string($cleaned)) {
            $cleaned = '';
        }

        $cleaned = preg_replace('/\x00/', '', $cleaned) ?? '';

        if (! mb_check_encoding($cleaned, 'UTF-8')) {
            $cleaned = iconv('UTF-8', 'UTF-8//IGNORE', $cleaned) ?: '';
        }

        return $cleaned;
    }
}
