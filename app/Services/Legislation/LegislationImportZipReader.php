<?php

namespace App\Services\Legislation;

use App\DTO\Legislation\LegislationImportZipEntry;
use App\Enums\LegislationKind;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

class LegislationImportZipReader
{
    public function __construct(private readonly LegislationCitationNormalizer $normalizer) {}

    /**
     * @return list<LegislationImportZipEntry>
     */
    public function list(string $absolutePath, LegislationKind $kind): array
    {
        $zip = $this->open($absolutePath);
        $entries = [];
        $limit = (int) config('sentria.legislation.import_max_files', 500);

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (! is_string($name) || $name === '' || $this->shouldSkip($name)) {
                    continue;
                }

                if (count($entries) >= $limit) {
                    throw new InvalidArgumentException('The ZIP contains more PDF files than the import limit.');
                }

                $basename = basename(str_replace('\\', '/', $name));

                $entries[] = new LegislationImportZipEntry(
                    entryName: $name,
                    basename: $basename,
                    key: $this->normalizer->fromFilename($basename, $kind),
                );
            }
        } finally {
            $zip->close();
        }

        return $entries;
    }

    /**
     * Extract PDFs into `$destinationDirectory` on the given disk. Keys are
     * ZIP entry names; values are relative storage paths.
     *
     * @return array<string, string>
     */
    public function extract(string $absolutePath, string $disk, string $destinationDirectory): array
    {
        $zip = $this->open($absolutePath);
        $extracted = [];

        Storage::disk($disk)->makeDirectory($destinationDirectory);

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (! is_string($name) || $name === '' || $this->shouldSkip($name)) {
                    continue;
                }

                $contents = $zip->getFromIndex($index);

                if (! is_string($contents) || $contents === '') {
                    continue;
                }

                $relative = $destinationDirectory.'/'.$this->safeStoredName($name);
                Storage::disk($disk)->put($relative, $contents);
                $extracted[$name] = $relative;
            }
        } finally {
            $zip->close();
        }

        return $extracted;
    }

    private function open(string $absolutePath): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new InvalidArgumentException('The ZIP archive could not be opened.');
        }

        return $zip;
    }

    private function shouldSkip(string $name): bool
    {
        $normalized = str_replace('\\', '/', $name);

        if ($normalized === '' || str_ends_with($normalized, '/')) {
            return true;
        }

        if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            return true;
        }

        $basename = basename($normalized);

        if ($basename === '' || str_starts_with($basename, '.') || str_starts_with($normalized, '__MACOSX/')) {
            return true;
        }

        return strtolower((string) pathinfo($basename, PATHINFO_EXTENSION)) !== 'pdf';
    }

    private function safeStoredName(string $entryName): string
    {
        $basename = basename(str_replace('\\', '/', $entryName));
        $hash = substr(hash('sha256', $entryName), 0, 12);

        return $hash.'-'.Str::slug(pathinfo($basename, PATHINFO_FILENAME)).'.pdf';
    }
}
