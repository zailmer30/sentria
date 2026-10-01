<?php

namespace App\Services\Branding;

/**
 * Derives the deep "plate" family — chamber floor, record heroes, the
 * secretariat desk, the portal plate and the sign-in panel — from one hex.
 * Every surface in this family carries white text, so a plate is only
 * accepted when white on it clears 4.5:1. Output is hex-only.
 */
class PlatePalette
{
    public const DEFAULT = '#132042';

    public const MIN_CONTRAST = 4.5;

    public function __construct(private readonly AccentPalette $color) {}

    public function normalize(string $hex): ?string
    {
        return $this->color->normalize($hex);
    }

    public function isDefault(string $hex): bool
    {
        return $this->normalize($hex) === self::DEFAULT;
    }

    public function isReadable(string $hex): bool
    {
        $plate = $this->normalize($hex);

        return $plate !== null && $this->color->contrastRatio($plate, '#FFFFFF') >= self::MIN_CONTRAST;
    }

    /**
     * @return array{floor: array<string, string>, desk: array<string, string>, portal: array<string, string>, login: array<string, string>}|null
     */
    public function derive(string $hex): ?array
    {
        $plate = $this->normalize($hex);

        if ($plate === null || ! $this->isReadable($plate)) {
            return null;
        }

        $readableTint = $this->readableTint($plate);

        return [
            'floor' => [
                'plate' => $plate,
                'mid' => $this->shift($plate, 0.025),
                'end' => $this->shift($plate, 0.062),
                'hover' => $this->shift($plate, 0.025),
                'muted' => $this->towardWhite($plate, 7.0),
                'faint' => $this->towardWhite($plate, self::MIN_CONTRAST),
            ],
            'desk' => [
                'start' => $this->shift($plate, -0.02),
                'mid' => $this->shift($plate, 0.035),
                'end' => $this->shift($plate, 0.105),
                'ring' => $this->color->withLightness($plate, 0.74, min(0.6, $this->color->saturation($plate))),
            ],
            'portal' => [
                'accent' => $plate,
                'hover' => $this->shift($plate, 0.09),
                'soft' => $this->color->mix($plate, '#FFFFFF', 0.9),
                'line' => $this->color->mix($plate, '#FFFFFF', 0.7),
                'tint' => $readableTint,
            ],
            'login' => [
                'navy' => $plate,
                'hover' => $this->shift($plate, -0.06),
                'from' => $this->shift($plate, -0.04),
                'to' => $this->shift($plate, 0.03),
            ],
        ];
    }

    public function css(string $hex): string
    {
        $palette = $this->derive($hex);

        if ($palette === null) {
            return '';
        }

        foreach ($palette as $group) {
            foreach ($group as $token) {
                if ($this->normalize($token) === null) {
                    return '';
                }
            }
        }

        ['floor' => $floor, 'desk' => $desk, 'portal' => $portal, 'login' => $login] = $palette;

        return implode("\n", [
            'html.sentria {',
            "    --color-floor-plate: {$floor['plate']};",
            "    --color-floor-plate-mid: {$floor['mid']};",
            "    --color-floor-plate-end: {$floor['end']};",
            "    --color-floor-plate-hover: {$floor['hover']};",
            "    --color-floor-ink-muted: {$floor['muted']};",
            "    --color-floor-ink-faint: {$floor['faint']};",
            "    --color-desk-plate: {$desk['start']};",
            "    --color-desk-plate-mid: {$desk['mid']};",
            "    --color-desk-plate-end: {$desk['end']};",
            "    --color-desk-ring: {$desk['ring']};",
            '}',
            'html.sentria .portal-shell {',
            "    --color-accent: {$portal['accent']};",
            "    --color-accent-hover: {$portal['hover']};",
            "    --color-accent-soft: {$portal['soft']};",
            "    --color-accent-line: {$portal['line']};",
            "    --color-accent-ink: {$portal['tint']};",
            "    --color-brand: {$portal['tint']};",
            "    --color-focus: {$portal['tint']};",
            '}',
            'html.sentria .login-shell {',
            "    --login-navy: {$login['navy']};",
            "    --login-navy-hover: {$login['hover']};",
            "    --login-brand-from: {$login['from']};",
            "    --login-brand-to: {$login['to']};",
            '}',
        ]);
    }

    /**
     * Moves lightness by $delta, but never so far up that white text on the
     * result drops below the readable floor.
     */
    private function shift(string $plate, float $delta): string
    {
        $base = $this->color->lightness($plate);
        $candidate = $this->color->withLightness($plate, $base + $delta);

        if ($delta <= 0) {
            return $candidate;
        }

        for ($step = $delta; $step > 0 && ! $this->isReadable($candidate); $step -= 0.005) {
            $candidate = $this->color->withLightness($plate, $base + $step);
        }

        return $this->isReadable($candidate) ? $candidate : $plate;
    }

    /**
     * The darkest white-to-plate mix that still clears $target against the
     * plate, so secondary text keeps the plate's hue without going grey-on-grey.
     */
    private function towardWhite(string $plate, float $target): string
    {
        for ($t = 0.6; $t > 0; $t -= 0.02) {
            $candidate = $this->color->mix('#FFFFFF', $plate, $t);

            if ($this->color->contrastRatio($candidate, $plate) >= $target) {
                return $candidate;
            }
        }

        return '#FFFFFF';
    }

    /**
     * A mid-tone of the plate's hue that reads as text/links on white.
     */
    private function readableTint(string $plate): string
    {
        for ($l = 0.43; $l > 0.1; $l -= 0.02) {
            $candidate = $this->color->withLightness($plate, $l);

            if ($this->color->contrastRatio($candidate, '#FFFFFF') >= self::MIN_CONTRAST) {
                return $candidate;
            }
        }

        return $plate;
    }
}
