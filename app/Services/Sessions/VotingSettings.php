<?php

namespace App\Services\Sessions;

use App\Models\SystemSetting;

class VotingSettings
{
    public function electronicIsBinding(): bool
    {
        $value = $this->settingScalar('voting.electronic_is_binding');

        if ($value === null) {
            return (bool) config('sentria.voting.electronic_is_binding', false);
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
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

        return json_decode((string) $value, true);
    }
}
