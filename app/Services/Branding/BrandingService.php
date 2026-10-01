<?php

namespace App\Services\Branding;

use App\DTO\Branding\BrandingSnapshot;
use App\Enums\PlatePattern;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class BrandingService
{
    public const CACHE_KEY = 'sentria.branding';

    public function __construct(
        private readonly AccentPalette $palette,
        private readonly PlatePalette $plates,
        private readonly BrandLogoService $logos,
        private readonly AuditLogger $audit,
    ) {}

    public function snapshot(): BrandingSnapshot
    {
        if (app()->runningUnitTests()) {
            return $this->load();
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return BrandingSnapshot::fromCache($cached);
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        $snapshot = $this->load();
        Cache::put(self::CACHE_KEY, $snapshot->toCache(), 3600);

        return $snapshot;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function css(): string
    {
        try {
            $snapshot = $this->snapshot();
        } catch (Throwable) {
            return '';
        }

        $blocks = [];

        if (! $this->palette->isDefault($snapshot->accent)) {
            $blocks[] = $this->palette->css($snapshot->accent);
        }

        if (! $this->plates->isDefault($snapshot->plate)) {
            $blocks[] = $this->plates->css($snapshot->plate);
        }

        return implode("\n", array_filter($blocks, fn (string $block): bool => $block !== ''));
    }

    /**
     * The `data-plate-pattern` value for the root element, or null to keep each
     * plate's authored texture.
     */
    public function platePatternAttribute(): ?string
    {
        try {
            $pattern = $this->snapshot()->platePattern;
        } catch (Throwable) {
            return null;
        }

        return $pattern === PlatePattern::Authored ? null : $pattern->value;
    }

    /**
     * @return array{name: string, short_name: string, locality: string, accent: string, plate: string, plate_pattern: string}
     */
    public function defaults(): array
    {
        return [
            'name' => (string) config('sentria.organization.name'),
            'short_name' => (string) config('sentria.organization.short_name'),
            'locality' => (string) config('sentria.organization.locality'),
            'accent' => $this->palette->normalize((string) config('sentria.branding.accent', AccentPalette::DEFAULT))
                ?? AccentPalette::DEFAULT,
            'plate' => $this->readablePlate((string) config('sentria.branding.plate', PlatePalette::DEFAULT))
                ?? PlatePalette::DEFAULT,
            'plate_pattern' => PlatePattern::fromSetting(config('sentria.branding.plate_pattern'))->value,
        ];
    }

    public function save(
        string $name,
        string $shortName,
        string $locality,
        string $accent,
        ?UploadedFile $logo,
        bool $removeLogo,
        ?User $actor,
        ?string $plate = null,
        ?PlatePattern $platePattern = null,
    ): BrandingSnapshot {
        $normalizedAccent = $this->palette->normalize($accent) ?? AccentPalette::DEFAULT;
        $before = $this->snapshot();
        $normalizedPlate = $plate === null ? $before->plate : ($this->readablePlate($plate) ?? PlatePalette::DEFAULT);
        $pattern = $platePattern ?? $before->platePattern;

        DB::transaction(function () use ($name, $shortName, $locality, $normalizedAccent, $normalizedPlate, $pattern, $logo, $removeLogo, $actor, $before): void {
            $logoPath = $before->logoPath;

            if ($logo instanceof UploadedFile) {
                $logoPath = $this->logos->store($logo, $before->logoPath);
            } elseif ($removeLogo) {
                $this->logos->remove($before->logoPath);
                $logoPath = null;
            }

            $this->put('organization.name', 'general', 'Organization name', 'Name of the legislative body as it appears on official output.', 'string', $name, $actor);
            $this->put('organization.short_name', 'general', 'Organization short name', 'Short label shown in the navigation rail and compact chrome.', 'string', $shortName, $actor);
            $this->put('organization.locality', 'general', 'Locality', 'Province, city, or municipality served by this installation.', 'string', $locality, $actor);
            $this->put('branding.accent', 'general', 'Accent color', 'Primary action colour. Live/red is not customizable.', 'string', $normalizedAccent, $actor);
            $this->put('branding.plate', 'general', 'Plate color', 'Deep colour behind hero cards, the chamber floor, the portal plate, and sign-in. Must carry white text.', 'string', $normalizedPlate, $actor);
            $this->put('branding.plate_pattern', 'general', 'Plate pattern', 'Texture over every plate, or authored to keep each surface\'s own.', 'string', $pattern->value, $actor);
            $this->put('branding.logo_path', 'general', 'Brand logo', 'Official seal shown in place of the lettermark.', 'string', $logoPath, $actor);
        });

        $this->forget();
        $after = $this->snapshot();

        $this->audit->record(
            event: 'settings.branding.updated',
            category: 'settings',
            actor: $actor,
            old: [
                'name' => $before->name,
                'short_name' => $before->shortName,
                'locality' => $before->locality,
                'accent' => $before->accent,
                'plate' => $before->plate,
                'plate_pattern' => $before->platePattern->value,
                'logo_path' => $before->logoPath,
            ],
            new: [
                'name' => $after->name,
                'short_name' => $after->shortName,
                'locality' => $after->locality,
                'accent' => $after->accent,
                'plate' => $after->plate,
                'plate_pattern' => $after->platePattern->value,
                'logo_path' => $after->logoPath,
            ],
        );

        return $after;
    }

    public function resetToDefaults(?User $actor): BrandingSnapshot
    {
        $defaults = $this->defaults();

        return $this->save(
            name: $defaults['name'],
            shortName: $defaults['short_name'],
            locality: $defaults['locality'],
            accent: $defaults['accent'],
            logo: null,
            removeLogo: true,
            actor: $actor,
            plate: $defaults['plate'],
            platePattern: PlatePattern::from($defaults['plate_pattern']),
        );
    }

    private function readablePlate(string $hex): ?string
    {
        return $this->plates->isReadable($hex) ? $this->plates->normalize($hex) : null;
    }

    private function load(): BrandingSnapshot
    {
        $defaults = $this->defaults();
        $accent = $this->palette->normalize($this->stringSetting('branding.accent', $defaults['accent']))
            ?? $defaults['accent'];
        $plate = $this->readablePlate($this->stringSetting('branding.plate', $defaults['plate']))
            ?? $defaults['plate'];
        $platePattern = PlatePattern::fromSetting($this->stringSetting('branding.plate_pattern', $defaults['plate_pattern']));
        $logoPath = $this->nullableStringSetting('branding.logo_path');
        $logoSetting = SystemSetting::query()->where('key', 'branding.logo_path')->first();
        $version = $logoSetting?->updated_at?->getTimestamp();

        return new BrandingSnapshot(
            name: $this->stringSetting('organization.name', $defaults['name']),
            shortName: $this->stringSetting('organization.short_name', $defaults['short_name']),
            locality: $this->stringSetting('organization.locality', $defaults['locality']),
            accent: $accent,
            plate: $plate,
            platePattern: $platePattern,
            logoPath: $logoPath,
            logoUrl: $this->logos->url($logoPath, $version),
        );
    }

    private function put(
        string $key,
        string $group,
        string $label,
        string $description,
        string $type,
        mixed $value,
        ?User $actor,
    ): void {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'label' => $label,
                'description' => $description,
                'type' => $type,
                'value' => $value,
                'is_public' => true,
                'is_locked' => false,
                'updated_by' => $actor?->getKey(),
            ],
        );
    }

    private function stringSetting(string $key, string $fallback): string
    {
        $value = $this->settingValue($key);

        if (! is_string($value)) {
            return $fallback;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? $fallback : $trimmed;
    }

    private function nullableStringSetting(string $key): ?string
    {
        $value = $this->settingValue($key);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function settingValue(string $key): mixed
    {
        $setting = SystemSetting::query()->where('key', $key)->first();

        if ($setting === null) {
            return null;
        }

        $value = $setting->getRawOriginal('value');

        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
