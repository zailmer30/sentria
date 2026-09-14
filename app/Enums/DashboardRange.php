<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * The reporting window a dashboard is counting over. Fixed options rather than
 * a free date range: the dashboard answers "what needs me now", and an
 * arbitrary historical window is a report, not a dashboard.
 */
enum DashboardRange: string
{
    case Week = '7d';
    case Month = '30d';
    case Year = 'ytd';

    public static function fromRequest(?string $value): self
    {
        // The chart used to offer a 90-day window; bookmarks still land on year.
        if ($value === '90d') {
            return self::Year;
        }

        return self::tryFrom((string) $value) ?? self::Month;
    }

    public function days(): int
    {
        return match ($this) {
            self::Week => 7,
            self::Month => 30,
            self::Year => max(1, (int) CarbonImmutable::now()->startOfDay()->diffInDays(CarbonImmutable::now()->startOfYear()) + 1),
        };
    }

    public function since(): CarbonImmutable
    {
        return match ($this) {
            self::Year => CarbonImmutable::now()->startOfYear(),
            default => CarbonImmutable::now()->startOfDay()->subDays($this->days() - 1),
        };
    }

    /**
     * Start of the equally sized window immediately before this one, used for
     * the period-over-period comparison on each figure.
     */
    public function previousSince(): CarbonImmutable
    {
        return match ($this) {
            self::Year => $this->since()->subYear(),
            default => $this->since()->subDays($this->days()),
        };
    }

    /**
     * Daily bars for a week, weekly bars for a month, monthly bars for the year.
     * The month window is five weeks at dashboard width — daily ticks would collide.
     */
    public function bucket(): string
    {
        return match ($this) {
            self::Week => 'day',
            self::Month => 'week',
            self::Year => 'month',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $range): array => [
            'value' => $range->value,
            'label' => match ($range) {
                self::Year => 'Ytd',
                default => $range->value,
            },
            'description' => "dashboard.range.{$range->value}",
        ], self::cases());
    }
}
