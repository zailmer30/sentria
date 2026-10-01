<?php

use App\Services\Branding\AccentPalette;
use App\Services\Branding\PlatePalette;

function platePalette(): PlatePalette
{
    return new PlatePalette(new AccentPalette);
}

it('treats the authored navy as the default plate', function (): void {
    expect(platePalette()->isDefault('#132042'))->toBeTrue()
        ->and(platePalette()->isDefault('#7A0F24'))->toBeFalse();
});

it('only accepts plates that carry white text', function (): void {
    expect(platePalette()->isReadable('#7A0F24'))->toBeTrue()
        ->and(platePalette()->isReadable('#0F172A'))->toBeTrue()
        ->and(platePalette()->isReadable('#F5D76E'))->toBeFalse()
        ->and(platePalette()->isReadable('#8ECAE6'))->toBeFalse()
        ->and(platePalette()->derive('#F5D76E'))->toBeNull()
        ->and(platePalette()->css('#F5D76E'))->toBe('');
});

it('keeps every white-text stop of a derived plate readable', function (string $plate): void {
    $color = new AccentPalette;
    $palette = platePalette()->derive($plate);

    expect($palette)->not->toBeNull();

    foreach (['plate', 'mid', 'end', 'hover'] as $key) {
        expect($color->contrastRatio($palette['floor'][$key], '#FFFFFF'))->toBeGreaterThanOrEqual(4.5);
    }

    expect($color->contrastRatio($palette['floor']['faint'], $plate))->toBeGreaterThanOrEqual(4.5)
        ->and($color->contrastRatio($palette['portal']['tint'], '#FFFFFF'))->toBeGreaterThanOrEqual(4.5);
})->with(['#132042', '#7A0F24', '#14532D', '#5C3D07', '#1D4ED8']);

it('emits hex-only CSS for the floor, portal, and sign-in plates', function (): void {
    $css = platePalette()->css('#7A0F24');

    expect($css)->toContain('html.sentria {')
        ->and($css)->toContain('--color-floor-plate: #7A0F24')
        ->and($css)->toContain('--color-desk-plate:')
        ->and($css)->toContain('html.sentria .portal-shell {')
        ->and($css)->toContain('html.sentria .login-shell {')
        ->and($css)->toContain('--login-navy: #7A0F24')
        ->and($css)->not->toContain('<')
        ->and($css)->not->toContain('url(');

    expect(preg_match('/:[^;]*[^#0-9A-Fa-f;{} \n]/', $css))->toBe(0);
});
