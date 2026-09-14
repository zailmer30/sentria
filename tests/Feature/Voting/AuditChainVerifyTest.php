<?php

use App\Models\AuditLog;
use App\Services\Audit\AuditChainHasher;
use App\Services\Audit\AuditLogger;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

it('passes verification for a valid audit chain', function (): void {
    $logger = app(AuditLogger::class);
    $hasher = app(AuditChainHasher::class);

    $logger->record('session.started', 'session', message: 'First');
    $logger->record('vote.cast', 'session', message: 'Second');
    $logger->record('session.adjourned', 'session', message: 'Third');

    AuditLog::query()->orderBy('sequence')->each(function (AuditLog $log) use ($hasher): void {
        expect($hasher->hashFromLog($log))->toBe($log->hash);
    });

    Artisan::call('audit:verify-chain');

    expect(Artisan::output())->toContain('verified');
});

it('fails verification when a hash is corrupted', function (): void {
    $logger = app(AuditLogger::class);
    $logger->record('session.started', 'session', message: 'Integrity check');

    $log = AuditLog::query()->orderBy('sequence')->firstOrFail();

    DB::unprepared('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_append_only');
    DB::table('audit_logs')->where('id', $log->getKey())->update(['hash' => str_repeat('a', 64)]);
    DB::unprepared('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_append_only');

    $exitCode = Artisan::call('audit:verify-chain');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Hash mismatch');
});

it('hashes payloads consistently between write and verify', function (): void {
    $logger = app(AuditLogger::class);
    $log = $logger->record('consistency.test', 'general', message: 'canonical')->fresh();

    $hasher = app(AuditChainHasher::class);

    expect($hasher->hashFromLog($log))->toBe($log->hash);
});
