<?php

use App\Models\CommitteeReport;
use App\Services\Committees\CommitteeReportNumberAllocator;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-08-31 12:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('starts a series at 001 for the current year', function (): void {
    $allocator = app(CommitteeReportNumberAllocator::class);

    expect($allocator->preview())->toBe('CR-2026-001');
});

it('increments from the highest number in the same year', function (): void {
    CommitteeReport::factory()->create(['report_number' => 'CR-2026-002']);
    CommitteeReport::factory()->create(['report_number' => 'CR-2026-001']);
    CommitteeReport::factory()->create(['report_number' => 'CR-2025-099']);

    $allocator = app(CommitteeReportNumberAllocator::class);

    expect($allocator->preview())->toBe('CR-2026-003')
        ->and($allocator->allocate())->toBe('CR-2026-003');
});

it('does not reuse a number from a soft-deleted report', function (): void {
    $report = CommitteeReport::factory()->create(['report_number' => 'CR-2026-001']);
    $report->delete();

    $allocator = app(CommitteeReportNumberAllocator::class);

    expect($allocator->allocate())->toBe('CR-2026-002');
});
