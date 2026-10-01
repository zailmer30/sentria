<?php

namespace App\Services\Branding;

/**
 * Derives the accent token family from a single hex. Live/red and neutrals
 * stay authored in CSS. Output is hex-only so it is safe to print into Blade.
 */
class AccentPalette
{
    public const DEFAULT = '#0038A8';

    public function normalize(string $hex): ?string
    {
        $value = strtoupper(trim($hex));

        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, '#')) {
            $value = '#'.$value;
        }

        if (preg_match('/^#([0-9A-F]{3})$/', $value, $short) === 1) {
            $digits = $short[1];

            return sprintf('#%s%s%s%s%s%s', $digits[0], $digits[0], $digits[1], $digits[1], $digits[2], $digits[2]);
        }

        if (preg_match('/^#([0-9A-F]{6})$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    public function isDefault(string $hex): bool
    {
        $normalized = $this->normalize($hex);

        return $normalized !== null && $normalized === self::DEFAULT;
    }

    /**
     * @return array{light: array<string, string>, dark: array<string, string>}|null
     */
    public function derive(string $hex): ?array
    {
        $accent = $this->normalize($hex);

        if ($accent === null) {
            return null;
        }

        $lightOn = $this->onColor($accent);
        $darkAccent = $this->lightenForDark($accent);
        $darkOn = $this->onColor($darkAccent);

        return [
            'light' => [
                'accent' => $accent,
                'hover' => $this->mix($accent, '#000000', 0.18),
                'ink' => $this->mix($accent, '#000000', 0.08),
                'soft' => $this->mix($accent, '#FFFFFF', 0.88),
                'line' => $this->mix($accent, '#FFFFFF', 0.55),
                'on' => $lightOn,
            ],
            'dark' => [
                'accent' => $darkAccent,
                'hover' => $this->mix($darkAccent, '#FFFFFF', 0.16),
                'ink' => $this->mix($darkAccent, '#FFFFFF', 0.28),
                'soft' => $this->mix($accent, '#0F1419', 0.82),
                'line' => $this->mix($accent, '#0F1419', 0.58),
                'on' => $darkOn,
            ],
        ];
    }

    public function css(string $hex): string
    {
        $palette = $this->derive($hex);

        if ($palette === null) {
            return '';
        }

        return $this->block('html.sentria', $palette['light'])."\n".$this->block('html.sentria.dark', $palette['dark']);
    }

    public function contrastRatio(string $a, string $b): float
    {
        $left = $this->relativeLuminance($a);
        $right = $this->relativeLuminance($b);
        $lighter = max($left, $right);
        $darker = min($left, $right);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public function onColor(string $background): string
    {
        $white = $this->contrastRatio($background, '#FFFFFF');
        $ink = $this->contrastRatio($background, '#0F1419');

        return $white >= $ink ? '#FFFFFF' : '#0F1419';
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function block(string $selector, array $tokens): string
    {
        foreach ($tokens as $token) {
            if ($this->normalize($token) === null) {
                return '';
            }
        }

        $lines = [
            "{$selector} {",
            "    --color-accent: {$tokens['accent']};",
            "    --color-accent-hover: {$tokens['hover']};",
            "    --color-accent-ink: {$tokens['ink']};",
            "    --color-accent-soft: {$tokens['soft']};",
            "    --color-accent-line: {$tokens['line']};",
            "    --color-accent-on: {$tokens['on']};",
            "    --color-focus: {$tokens['accent']};",
            "    --color-chart-1: {$tokens['accent']};",
            '}',
        ];

        return implode("\n", $lines);
    }

    private function lightenForDark(string $hex): string
    {
        [$r, $g, $b] = $this->rgb($hex);
        [$h, $s, $l] = $this->toHsl($r, $g, $b);

        if ($l < 0.55) {
            $l = min(0.68, $l + 0.32);
        }

        return $this->fromHsl($h, $s, $l);
    }

    public function lightness(string $hex): float
    {
        return $this->toHsl(...$this->rgb($hex))[2];
    }

    /**
     * Same hue and saturation, new HSL lightness (0–1, clamped).
     */
    public function withLightness(string $hex, float $lightness, ?float $saturation = null): string
    {
        [$h, $s] = $this->toHsl(...$this->rgb($hex));

        return $this->fromHsl($h, max(0.0, min(1.0, $saturation ?? $s)), max(0.0, min(1.0, $lightness)));
    }

    public function saturation(string $hex): float
    {
        return $this->toHsl(...$this->rgb($hex))[1];
    }

    public function hue(string $hex): float
    {
        return $this->toHsl(...$this->rgb($hex))[0] * 360;
    }

    public function mix(string $from, string $toward, float $amountOfToward): string
    {
        [$fr, $fg, $fb] = $this->rgb($from);
        [$tr, $tg, $tb] = $this->rgb($toward);
        $t = max(0.0, min(1.0, $amountOfToward));

        return $this->hex(
            (int) round($fr + ($tr - $fr) * $t),
            (int) round($fg + ($tg - $fg) * $t),
            (int) round($fb + ($tb - $fb) * $t),
        );
    }

    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = $this->rgb($hex);

        return 0.2126 * $this->linearChannel($r)
            + 0.7152 * $this->linearChannel($g)
            + 0.0722 * $this->linearChannel($b);
    }

    private function linearChannel(int $value): float
    {
        $channel = $value / 255;

        return $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $hex): array
    {
        $normalized = $this->normalize($hex) ?? '#000000';
        $digits = substr($normalized, 1);

        return [
            hexdec(substr($digits, 0, 2)),
            hexdec(substr($digits, 2, 2)),
            hexdec(substr($digits, 4, 2)),
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function toHsl(int $r, int $g, int $b): array
    {
        $rf = $r / 255;
        $gf = $g / 255;
        $bf = $b / 255;
        $max = max($rf, $gf, $bf);
        $min = min($rf, $gf, $bf);
        $l = ($max + $min) / 2;
        $delta = $max - $min;

        if ($delta < 0.00001) {
            return [0.0, 0.0, $l];
        }

        $s = $l > 0.5 ? $delta / (2.0 - $max - $min) : $delta / ($max + $min);

        $h = match ($max) {
            $rf => fmod((($gf - $bf) / $delta) + ($gf < $bf ? 6 : 0), 6) / 6,
            $gf => ((($bf - $rf) / $delta) + 2) / 6,
            default => ((($rf - $gf) / $delta) + 4) / 6,
        };

        return [$h, $s, $l];
    }

    private function fromHsl(float $h, float $s, float $l): string
    {
        if ($s <= 0.00001) {
            $channel = (int) round($l * 255);

            return $this->hex($channel, $channel, $channel);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return $this->hex(
            (int) round($this->hueToRgb($p, $q, $h + 1 / 3) * 255),
            (int) round($this->hueToRgb($p, $q, $h) * 255),
            (int) round($this->hueToRgb($p, $q, $h - 1 / 3) * 255),
        );
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    private function hex(int $r, int $g, int $b): string
    {
        return sprintf(
            '#%02X%02X%02X',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b)),
        );
    }
}
