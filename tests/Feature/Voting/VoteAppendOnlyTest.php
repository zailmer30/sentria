<?php

use App\Enums\VoteChoice;
use App\Models\AuditLog;
use App\Models\Vote;
use App\Services\Audit\AuditLogger;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

it('refuses vote updates at the database layer', function (): void {
    $vote = Vote::factory()->create();

    expect(fn () => DB::table('votes')->where('id', $vote->getKey())->update(['choice' => 'no']))
        ->toThrow(QueryException::class);
});

it('refuses vote deletes at the database layer', function (): void {
    $vote = Vote::factory()->create();

    expect(fn () => DB::table('votes')->where('id', $vote->getKey())->delete())
        ->toThrow(QueryException::class);
});

it('refuses audit log updates at the database layer', function (): void {
    app(AuditLogger::class)->record('test.event', 'general', message: 'seed');

    $log = AuditLog::query()->firstOrFail();

    expect(fn () => DB::table('audit_logs')->where('id', $log->getKey())->update(['hash' => 'bad']))
        ->toThrow(QueryException::class);
});

it('refuses audit log deletes at the database layer', function (): void {
    app(AuditLogger::class)->record('test.event', 'general', message: 'seed');

    $log = AuditLog::query()->firstOrFail();

    expect(fn () => DB::table('audit_logs')->where('id', $log->getKey())->delete())
        ->toThrow(QueryException::class);
});

it('refuses vote updates at the eloquent layer', function (): void {
    $vote = Vote::factory()->choice(VoteChoice::Yes)->create();

    expect(fn () => $vote->update(['choice' => 'no']))
        ->toThrow(RuntimeException::class);
});
