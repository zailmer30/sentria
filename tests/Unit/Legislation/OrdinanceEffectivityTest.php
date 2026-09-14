<?php

use App\Models\Ordinance;

it('uses the proposed effectivity when it is a positive number of days', function (): void {
    expect(Ordinance::daysUntilEffectivity(15))->toBe(15)
        ->and(Ordinance::daysUntilEffectivity('7'))->toBe(7);
});

it('falls back to ten days when proposed effectivity is missing or invalid', function (): void {
    expect(Ordinance::daysUntilEffectivity(null))->toBe(10)
        ->and(Ordinance::daysUntilEffectivity(0))->toBe(10)
        ->and(Ordinance::daysUntilEffectivity('soon'))->toBe(10);
});
