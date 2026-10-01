<?php

use App\Services\Branding\AccentPalette;

it('normalizes three-digit and six-digit hex', function (): void {
    $palette = new AccentPalette;

    expect($palette->normalize('#03a'))->toBe('#0033AA')
        ->and($palette->normalize('0038a8'))->toBe('#0038A8')
        ->and($palette->normalize('#0038A8'))->toBe('#0038A8')
        ->and($palette->normalize('  #ce1126  '))->toBe('#CE1126')
        ->and($palette->normalize('not-a-color'))->toBeNull()
        ->and($palette->normalize('#GG0000'))->toBeNull();
});

it('treats the national blue as the default accent', function (): void {
    $palette = new AccentPalette;

    expect($palette->isDefault('#0038a8'))->toBeTrue()
        ->and($palette->isDefault('#CE1126'))->toBeFalse();
});

it('picks dark text on a light accent and white text on a dark accent', function (): void {
    $palette = new AccentPalette;

    expect($palette->onColor('#F5D76E'))->toBe('#0F1419')
        ->and($palette->onColor('#0038A8'))->toBe('#FFFFFF')
        ->and($palette->contrastRatio('#0038A8', '#FFFFFF'))->toBeGreaterThan(4.5)
        ->and($palette->contrastRatio('#F5D76E', '#0F1419'))->toBeGreaterThan(4.5);
});

it('emits hex-only CSS variables for a custom accent', function (): void {
    $palette = new AccentPalette;
    $css = $palette->css('#C08A1E');

    expect($css)->toContain('html.sentria')
        ->and($css)->toContain('html.sentria.dark')
        ->and($css)->toContain('--color-accent: #C08A1E')
        ->and($css)->toContain('--color-focus: #C08A1E')
        ->and($css)->toContain('--color-chart-1: #C08A1E')
        ->and($css)->not->toContain('<')
        ->and($css)->not->toContain('url(')
        ->and($css)->not->toContain('javascript');

    preg_match_all('/#[0-9A-F]{6}/', $css, $matches);
    expect($matches[0])->not->toBeEmpty();

    expect(preg_match('/:[^;]*[^#0-9A-Fa-f;{} \n]/', $css))->toBe(0);
});
