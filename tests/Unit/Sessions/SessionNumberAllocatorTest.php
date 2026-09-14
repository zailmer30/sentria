<?php

use App\Enums\SessionType;
use App\Models\LegislativeSession;
use App\Services\Sessions\SessionNumberAllocator;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-08-31 12:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('starts a series at 00001 with an ordinal title', function (): void {
    $allocator = app(SessionNumberAllocator::class);

    expect($allocator->preview(SessionType::Regular))->toBe([
        'session_number' => 'RS-2026-00001',
        'title' => '1st Regular Session',
        'sequence' => 1,
    ]);
});

it('increments from the highest number in the same tag and year', function (): void {
    LegislativeSession::factory()->create([
        'type' => SessionType::Regular->value,
        'session_number' => 'RS-2026-00002',
        'legislative_year' => 2026,
    ]);
    LegislativeSession::factory()->create([
        'type' => SessionType::Special->value,
        'session_number' => 'SS-2026-00004',
        'legislative_year' => 2026,
    ]);
    LegislativeSession::factory()->create([
        'type' => SessionType::Regular->value,
        'session_number' => 'RS-2025-00099',
        'legislative_year' => 2025,
    ]);

    $allocator = app(SessionNumberAllocator::class);

    expect($allocator->preview(SessionType::Regular))->toMatchArray([
        'session_number' => 'RS-2026-00003',
        'title' => '3rd Regular Session',
        'sequence' => 3,
    ])->and($allocator->preview(SessionType::Special))->toMatchArray([
        'session_number' => 'SS-2026-00005',
        'title' => '5th Special Session',
        'sequence' => 5,
    ]);
});
