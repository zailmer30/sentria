<?php

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\Documents\DocumentTextStore;
use Illuminate\Support\Facades\DB;

it('round-trips unicode legal text through gzip compression', function (): void {
    $document = Document::factory()->create();
    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'extracted_text_compressed' => null,
        'text_extracted_at' => null,
    ]);

    $text = "SECTION 1. Short Title.\nOrdinansa ng Probinsya — ₱1,000,000.\n“Appropriation” for health.";

    $store = app(DocumentTextStore::class);
    $store->put($version, $text);

    $version->refresh();
    expect($store->get($version))->toBe($text)
        ->and($version->text_extracted_at)->not->toBeNull();

    $hex = DB::selectOne(
        'select length(extracted_text_compressed) as byte_len from document_versions where id = ?',
        [$version->getKey()],
    );
    expect((int) $hex->byte_len)->toBeGreaterThan(0);

    // Longer formal text compresses well; short strings may not.
    $long = str_repeat('SECTION 2. Appropriations. Funds are hereby authorized for provincial health. ', 80);
    $store->put($version, $long);
    $hexLong = DB::selectOne(
        'select length(extracted_text_compressed) as byte_len from document_versions where id = ?',
        [$version->getKey()],
    );
    expect((int) $hexLong->byte_len)->toBeLessThan(strlen($long))
        ->and($store->get($version))->toBe($long);
});

it('strips invalid utf-8 before storing', function (): void {
    $document = Document::factory()->create();
    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'extracted_text_compressed' => null,
    ]);

    $store = app(DocumentTextStore::class);
    $dirty = "SECTION 1.\n".chr(0xF8).'valid trail';
    $store->put($version, $dirty);

    $got = $store->get($version);
    expect($got)->not->toBeNull()
        ->and(mb_check_encoding((string) $got, 'UTF-8'))->toBeTrue()
        ->and($got)->not->toContain("\xF8");
});

it('stores null when text is empty after sanitize', function (): void {
    $document = Document::factory()->create();
    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
    ]);

    $store = app(DocumentTextStore::class);
    $store->put($version, "   \n\t  ");

    expect($store->get($version->refresh()))->toBeNull()
        ->and($version->text_extracted_at)->toBeNull();
});
