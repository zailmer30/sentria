<?php

namespace App\DTO\Branding;

use App\Enums\PlatePattern;
use App\Services\Branding\PlatePalette;

final readonly class BrandingSnapshot
{
    public function __construct(
        public string $name,
        public string $shortName,
        public string $locality,
        public string $accent,
        public ?string $logoPath,
        public ?string $logoUrl,
        public string $plate = PlatePalette::DEFAULT,
        public PlatePattern $platePattern = PlatePattern::Authored,
    ) {}

    /**
     * @return array{name: string, short_name: string, locality: string}
     */
    public function organization(): array
    {
        return [
            'name' => $this->name,
            'short_name' => $this->shortName,
            'locality' => $this->locality,
        ];
    }

    /**
     * @return array{logo_url: string|null, accent: string, plate: string, plate_pattern: string}
     */
    public function branding(): array
    {
        return [
            'logo_url' => $this->logoUrl,
            'accent' => $this->accent,
            'plate' => $this->plate,
            'plate_pattern' => $this->platePattern->value,
        ];
    }

    /**
     * @return array{name: string, short_name: string, locality: string, accent: string, plate: string, plate_pattern: string, logo_path: string|null, logo_url: string|null}
     */
    public function toCache(): array
    {
        return [
            'name' => $this->name,
            'short_name' => $this->shortName,
            'locality' => $this->locality,
            'accent' => $this->accent,
            'plate' => $this->plate,
            'plate_pattern' => $this->platePattern->value,
            'logo_path' => $this->logoPath,
            'logo_url' => $this->logoUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromCache(array $payload): self
    {
        $logoPath = $payload['logo_path'] ?? null;
        $logoUrl = $payload['logo_url'] ?? null;
        $plate = $payload['plate'] ?? null;

        return new self(
            name: is_string($payload['name'] ?? null) ? $payload['name'] : '',
            shortName: is_string($payload['short_name'] ?? null) ? $payload['short_name'] : '',
            locality: is_string($payload['locality'] ?? null) ? $payload['locality'] : '',
            accent: is_string($payload['accent'] ?? null) ? $payload['accent'] : '',
            logoPath: is_string($logoPath) && $logoPath !== '' ? $logoPath : null,
            logoUrl: is_string($logoUrl) && $logoUrl !== '' ? $logoUrl : null,
            plate: is_string($plate) && $plate !== '' ? $plate : PlatePalette::DEFAULT,
            platePattern: PlatePattern::fromSetting($payload['plate_pattern'] ?? null),
        );
    }
}
