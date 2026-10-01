<?php

namespace App\Services\Branding;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BrandLogoService
{
    public const DISK = 'public';

    public const DIRECTORY = 'branding';

    public const PATH = 'branding/logo.png';

    public function store(UploadedFile $file, ?string $previous): string
    {
        $png = $this->toTransparentPng($file);
        Storage::disk(self::DISK)->put(self::PATH, $png);

        if ($previous !== null && $previous !== '' && $previous !== self::PATH) {
            Storage::disk(self::DISK)->delete($previous);
        }

        return self::PATH;
    }

    public function remove(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);

        if ($path !== self::PATH) {
            Storage::disk(self::DISK)->delete(self::PATH);
        }
    }

    public function url(?string $path, ?int $version = null): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $url = '/storage/'.ltrim(str_replace('\\', '/', $path), '/');

        return $version ? "{$url}?v={$version}" : $url;
    }

    /**
     * Re-encode a seal as PNG and knock out light paper around it.
     * Official seals are often JPEG (or PNG with a white box) even when named .png.
     */
    public function toTransparentPng(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || $contents === '') {
            throw new RuntimeException('The seal could not be read.');
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('The seal could not be decoded.');
        }

        $prepared = $this->withAlpha($source);
        $this->knockOutPaper($prepared);
        $prepared = $this->cropToOpaque($prepared);

        ob_start();
        imagesavealpha($prepared, true);
        imagepng($prepared, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($prepared);

        if ($png === '') {
            throw new RuntimeException('The seal could not be encoded.');
        }

        return $png;
    }

    private function withAlpha(\GdImage $source): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagedestroy($source);

        return $canvas;
    }

    private function knockOutPaper(\GdImage $image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $seen = array_fill(0, $width * $height, false);
        $queue = [];
        $head = 0;

        $enqueue = function (int $x, int $y) use (&$seen, &$queue, $width): void {
            $index = $y * $width + $x;

            if ($seen[$index]) {
                return;
            }

            $seen[$index] = true;
            $queue[] = [$x, $y];
        };

        for ($x = 0; $x < $width; $x++) {
            if ($this->isPaper($image, $x, 0)) {
                $enqueue($x, 0);
            }

            if ($this->isPaper($image, $x, $height - 1)) {
                $enqueue($x, $height - 1);
            }
        }

        for ($y = 0; $y < $height; $y++) {
            if ($this->isPaper($image, 0, $y)) {
                $enqueue(0, $y);
            }

            if ($this->isPaper($image, $width - 1, $y)) {
                $enqueue($width - 1, $y);
            }
        }

        if ($queue === []) {
            return;
        }

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $removed = 0;

        while ($head < count($queue)) {
            [$x, $y] = $queue[$head];
            $head++;
            imagesetpixel($image, $x, $y, $transparent);
            $removed++;

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx < 0 || $ny < 0 || $nx >= $width || $ny >= $height) {
                    continue;
                }

                $index = $ny * $width + $nx;

                if ($seen[$index] || ! $this->isPaper($image, $nx, $ny)) {
                    continue;
                }

                $seen[$index] = true;
                $queue[] = [$nx, $ny];
            }
        }

        $kept = ($width * $height) - $removed;

        if ($kept < max(16, (int) floor($width * $height * 0.02))) {
            throw new RuntimeException('The seal has no usable subject after removing its background.');
        }
    }

    private function isPaper(\GdImage $image, int $x, int $y): bool
    {
        $rgba = imagecolorat($image, $x, $y);
        $alpha = ($rgba & 0x7F000000) >> 24;

        if ($alpha >= 64) {
            return false;
        }

        $red = ($rgba >> 16) & 0xFF;
        $green = ($rgba >> 8) & 0xFF;
        $blue = $rgba & 0xFF;
        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $lightness = ($max + $min) / 510;
        $chroma = $max - $min;

        return $lightness >= 0.58 && $chroma <= 92;
    }

    private function cropToOpaque(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $alpha = (imagecolorat($image, $x, $y) & 0x7F000000) >> 24;

                if ($alpha >= 120) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < $minX) {
            return $image;
        }

        $pad = 2;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($width - 1, $maxX + $pad);
        $maxY = min($height - 1, $maxY + $pad);
        $cropWidth = $maxX - $minX + 1;
        $cropHeight = $maxY - $minY + 1;

        if ($cropWidth === $width && $cropHeight === $height) {
            return $image;
        }

        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagecopy($cropped, $image, 0, 0, $minX, $minY, $cropWidth, $cropHeight);
        imagedestroy($image);

        return $cropped;
    }
}
