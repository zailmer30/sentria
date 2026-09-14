<?php

namespace App\Services\Sessions;

use App\Enums\ChamberFeed;
use App\Models\SystemSetting;

class ChamberCaptureSettings
{
    public function defaultFeed(): ChamberFeed
    {
        $value = $this->settingScalar('chamber.default_capture_mode');

        if (is_string($value) && $value !== '') {
            return ChamberFeed::fromMixed($value);
        }

        return ChamberFeed::default();
    }

    public function setDefaultFeed(ChamberFeed $feed): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'chamber.default_capture_mode'],
            [
                'group' => 'session',
                'label' => 'Default chamber capture mode',
                'description' => 'Mixer mix plus secretariat speaker assignment, or per-seat microphones.',
                'type' => 'select',
                'value' => $feed->value,
                'options' => ChamberFeed::values(),
                'is_public' => false,
                'is_locked' => false,
            ],
        );
    }

    /**
     * Audio input the recording computer should open. Null means PortAudio default
     * (or CHAMBER_DEVICE on the capture PC).
     *
     * @return array{index: int|null, name: string}|null
     */
    public function device(): ?array
    {
        $value = $this->settingScalar('chamber.device');

        if (! is_array($value)) {
            return null;
        }

        $name = isset($value['name']) && is_string($value['name']) ? trim($value['name']) : '';
        $index = isset($value['index']) && is_numeric($value['index']) ? (int) $value['index'] : null;

        if ($name === '' && $index === null) {
            return null;
        }

        return [
            'index' => $index,
            'name' => $name,
        ];
    }

    public function setDevice(?int $index, ?string $name): void
    {
        $trimmed = is_string($name) ? trim($name) : '';
        $value = ($index === null && $trimmed === '')
            ? null
            : [
                'index' => $index,
                'name' => $trimmed,
            ];

        SystemSetting::query()->updateOrCreate(
            ['key' => 'chamber.device'],
            [
                'group' => 'session',
                'label' => 'Chamber recording device',
                'description' => 'Audio input on the recording computer. Empty uses that computer’s default input.',
                'type' => 'json',
                'value' => $value,
                'options' => null,
                'is_public' => false,
                'is_locked' => false,
            ],
        );
    }

    private function settingScalar(string $key): mixed
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

        return json_decode($value, true);
    }
}
