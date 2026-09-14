<?php

use App\Models\DocumentVersion;
use App\Services\Documents\DocumentTextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

function storeExtractedText(DocumentVersion $version, string $text): DocumentVersion
{
    app(DocumentTextStore::class)->put($version, $text);

    return $version->refresh();
}
