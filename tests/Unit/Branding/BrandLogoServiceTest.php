<?php

use App\Services\Branding\BrandLogoService;
use Illuminate\Http\UploadedFile;

function paperSealUpload(): UploadedFile
{
    $image = imagecreatetruecolor(60, 60);
    $paper = imagecolorallocate($image, 255, 255, 255);
    $cream = imagecolorallocate($image, 206, 196, 137);
    $ink = imagecolorallocate($image, 20, 90, 40);
    imagefilledrectangle($image, 0, 0, 59, 59, $paper);
    imagefilledrectangle($image, 0, 48, 59, 59, $cream);
    imagefilledellipse($image, 30, 30, 24, 24, $ink);
    ob_start();
    imagejpeg($image, null, 90);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    $path = sys_get_temp_dir().'/sentria-seal-'.uniqid('', true).'.jpg';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'seal.jpg', 'image/jpeg', \UPLOAD_ERR_OK, true);
}

it('turns a jpeg paper background into a transparent png', function (): void {
    $png = app(BrandLogoService::class)->toTransparentPng(paperSealUpload());
    $decoded = imagecreatefromstring($png);

    expect($decoded)->toBeInstanceOf(GdImage::class)
        ->and(imageistruecolor($decoded))->toBeTrue();

    $corner = imagecolorat($decoded, 0, 0);
    $center = imagecolorat($decoded, (int) (imagesx($decoded) / 2), (int) (imagesy($decoded) / 2));

    expect(($corner & 0x7F000000) >> 24)->toBeGreaterThan(100)
        ->and((imagecolorat($decoded, 2, imagesy($decoded) - 1) & 0x7F000000) >> 24)->toBeGreaterThan(100)
        ->and(($center & 0x7F000000) >> 24)->toBeLessThan(20);

    imagedestroy($decoded);
});
