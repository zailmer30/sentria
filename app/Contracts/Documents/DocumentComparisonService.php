<?php

namespace App\Contracts\Documents;

use App\DTO\Documents\ComparisonResult;
use App\Models\DocumentVersion;

interface DocumentComparisonService
{
    public function compare(DocumentVersion $from, DocumentVersion $to): ComparisonResult;
}
