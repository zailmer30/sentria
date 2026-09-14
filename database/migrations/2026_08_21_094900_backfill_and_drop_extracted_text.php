<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('document_versions')
            ->select(['id', 'document_id', 'extracted_text'])
            ->whereNotNull('extracted_text')
            ->whereNull('extracted_text_compressed')
            ->orderBy('id')
            ->cursor();

        foreach ($rows as $row) {
            $text = $this->sanitizeUtf8((string) $row->extracted_text);

            if ($text === '') {
                continue;
            }

            $compressed = gzcompress($text, 9);

            if ($compressed === false) {
                continue;
            }

            DB::update(
                'update document_versions set extracted_text_compressed = decode(?, \'hex\'), text_extracted_at = coalesce(text_extracted_at, ?), updated_at = ? where id = ?',
                [bin2hex($compressed), now(), now(), $row->id],
            );

            $this->upsertSearchIndex((string) $row->document_id, (string) $row->id, $text);
        }

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn('extracted_text');
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->longText('extracted_text')->nullable()->after('ocr_status');
        });

        $rows = DB::table('document_versions')
            ->select(['id', 'extracted_text_compressed'])
            ->whereNotNull('extracted_text_compressed')
            ->orderBy('id')
            ->cursor();

        foreach ($rows as $row) {
            $compressed = $row->extracted_text_compressed;

            if (is_resource($compressed)) {
                $compressed = stream_get_contents($compressed) ?: '';
            }

            if (! is_string($compressed) || $compressed === '') {
                continue;
            }

            $text = @gzuncompress($compressed);

            if ($text === false) {
                continue;
            }

            DB::table('document_versions')
                ->where('id', $row->id)
                ->update(['extracted_text' => $text]);
        }
    }

    private function sanitizeUtf8(string $text): string
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

        return trim($cleaned);
    }

    private function upsertSearchIndex(string $documentId, string $versionId, string $body): void
    {
        $document = DB::table('documents')->where('id', $documentId)->first();

        if ($document === null) {
            return;
        }

        $title = (string) $document->title;
        $reference = (string) ($document->reference_number ?? '');
        $excerpt = Str::limit($body, 240, '…');
        $now = now();

        $existing = DB::table('document_search_indexes')->where('document_id', $documentId)->first();

        if ($existing === null) {
            $id = (string) Str::ulid();
            DB::table('document_search_indexes')->insert([
                'id' => $id,
                'document_id' => $documentId,
                'document_version_id' => $versionId,
                'excerpt' => $excerpt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $id = $existing->id;
            DB::table('document_search_indexes')->where('id', $id)->update([
                'document_version_id' => $versionId,
                'excerpt' => $excerpt,
                'updated_at' => $now,
            ]);
        }

        DB::update(
            <<<'SQL'
            update document_search_indexes
            set search_vector =
                setweight(to_tsvector('simple', coalesce(?, '')), 'A')
                || setweight(to_tsvector('simple', coalesce(?, '')), 'A')
                || setweight(to_tsvector('simple', coalesce(?, '')), 'D'),
                updated_at = ?
            where id = ?
            SQL,
            [$title, $reference, $body, $now, $id],
        );
    }
};
