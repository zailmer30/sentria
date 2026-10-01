<?php

namespace App\Enums;

/**
 * The texture laid over every deep plate. `Authored` keeps each surface's own
 * treatment (hairlines on the chamber floor, a grid at sign-in, bare record
 * heroes); every other case applies one texture to all plates. The value is
 * printed into `data-plate-pattern`, so cases must stay CSS-identifier safe.
 */
enum PlatePattern: string
{
    case Authored = 'authored';
    case None = 'none';
    case Lines = 'lines';
    case Grid = 'grid';
    case Dots = 'dots';
    case Diagonal = 'diagonal';
    case Glow = 'glow';

    public static function fromSetting(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Authored) : self::Authored;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $pattern): string => $pattern->value, self::cases());
    }
}
