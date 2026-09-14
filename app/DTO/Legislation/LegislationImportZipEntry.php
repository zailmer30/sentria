<?php

namespace App\DTO\Legislation;

final readonly class LegislationImportZipEntry
{
    public function __construct(
        public string $entryName,
        public string $basename,
        public ?LegislationCitationKey $key,
    ) {}
}
