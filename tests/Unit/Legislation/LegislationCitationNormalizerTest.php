<?php

use App\DTO\Legislation\LegislationCitationKey;
use App\Enums\LegislationKind;
use App\Services\Legislation\LegislationCitationNormalizer;

function citationKey(LegislationKind $kind, int $year, int $sequence): LegislationCitationKey
{
    return new LegislationCitationKey($kind, $year, $sequence);
}

it('normalizes register numbers against the series year', function (string $number, int $year, int $sequence): void {
    $key = app(LegislationCitationNormalizer::class)->fromRegister(LegislationKind::Ordinance, $number, $year);

    expect($key)->not->toBeNull()
        ->and($key?->value())->toBe(citationKey(LegislationKind::Ordinance, $year, $sequence)->value());
})->with([
    ['012', 2019, 12],
    ['ORD-2019-012', 2019, 12],
    ['Ordinance No. 12 s. 2019', 2019, 12],
    ['12', 2020, 12],
]);

it('parses common backfile filenames', function (string $filename, LegislationKind $kind, int $year, int $sequence): void {
    $key = app(LegislationCitationNormalizer::class)->fromFilename($filename, $kind);

    expect($key)->not->toBeNull()
        ->and($key?->value())->toBe(citationKey($kind, $year, $sequence)->value());
})->with([
    ['Ord. No. 12 s. 2019.pdf', LegislationKind::Ordinance, 2019, 12],
    ['ORD-2019-012.pdf', LegislationKind::Ordinance, 2019, 12],
    ['Ordinance No. 12-2019.pdf', LegislationKind::Ordinance, 2019, 12],
    ['12-2019.pdf', LegislationKind::Ordinance, 2019, 12],
    ['Resolution No. 45 s. 2020.pdf', LegislationKind::Resolution, 2020, 45],
    ['RES-2020-045.pdf', LegislationKind::Resolution, 2020, 45],
    ['folders/Ord. No. 7 s. 2018.pdf', LegislationKind::Ordinance, 2018, 7],
]);

it('rejects a resolution filename on an ordinance import', function (): void {
    $key = app(LegislationCitationNormalizer::class)->fromFilename(
        'Resolution No. 45 s. 2020.pdf',
        LegislationKind::Ordinance,
    );

    expect($key)->toBeNull();
});

it('rejects unreadable filenames', function (): void {
    $key = app(LegislationCitationNormalizer::class)->fromFilename('IMG_0042.pdf', LegislationKind::Ordinance);

    expect($key)->toBeNull();
});
