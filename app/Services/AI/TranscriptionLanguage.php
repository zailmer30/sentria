<?php

namespace App\Services\AI;

final class TranscriptionLanguage
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $code = strtolower(trim($value));

        return match ($code) {
            'filipino', 'fil', 'tagalog', 'tgl' => 'tl',
            'cebuano', 'bisaya', 'binisaya', 'visayan' => 'ceb',
            'english', 'eng' => 'en',
            default => substr($code, 0, 8),
        };
    }

    /**
     * Map Sentria language mode to an ElevenLabs language_code.
     * Returns null when the provider should auto-detect.
     */
    public static function elevenLabsCode(string $languageMode): ?string
    {
        if ($languageMode === '' || $languageMode === 'auto') {
            return null;
        }

        return match (strtolower($languageMode)) {
            'tl', 'fil', 'filipino', 'tagalog', 'tgl' => 'fil',
            'ceb', 'cebuano', 'bisaya', 'binisaya', 'visayan' => 'ceb',
            'en', 'eng', 'english' => 'en',
            default => $languageMode,
        };
    }
}
