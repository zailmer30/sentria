<?php

namespace App\Enums;

enum ChamberFeed: string
{
    case MixerMix = 'mixer_mix';
    case PerSeat = 'per_seat';

    public static function default(): self
    {
        return self::tryFrom((string) config('sentria.chamber.default_capture_mode', self::MixerMix->value))
            ?? self::MixerMix;
    }

    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value) ?? self::default();
    }

    public function usesDiarization(): bool
    {
        return $this === self::MixerMix;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $feed): string => $feed->value, self::cases());
    }
}
