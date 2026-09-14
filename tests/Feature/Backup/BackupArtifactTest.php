<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Backup\BackupService;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

afterEach(function (): void {
    $root = storage_path('app/backups');
    if (File::isDirectory($root)) {
        File::deleteDirectory($root);
    }
});

it('creates a backup manifest with database and document artifacts', function (): void {
    $privateRoot = storage_path('app/private/backups-test');
    File::ensureDirectoryExists($privateRoot);
    File::put($privateRoot.'/sample.txt', 'backup fixture');

    Artisan::call('sentria:backup');
    expect(Artisan::output())->toContain('Backup created');

    $backup = app(BackupService::class)->latestStatus();
    expect($backup)->not->toBeNull();

    $path = (string) $backup['path'];
    expect(File::isFile($path.'/manifest.json'))->toBeTrue();

    $verification = app(BackupService::class)->verifyArtifacts($path);
    expect($verification['database_files'])->toBeGreaterThan(0)
        ->and($verification['document_files'])->toBeGreaterThan(0)
        ->and($verification['size_bytes'])->toBeGreaterThan(0);

    File::delete($privateRoot.'/sample.txt');

    $restore = app(BackupService::class)->restore($path, force: true, restoreDatabase: false);
    expect($restore['restored']['documents']['files'])->toBeGreaterThan(0);
    expect(File::isFile($privateRoot.'/sample.txt'))->toBeTrue();
});

it('documents the offline vote queue contract key format', function (): void {
    $sessionId = '01JABCDEF0123456789012345';
    $agendaItemId = '01JABCDEF0123456789012346';
    $round = 2;

    $key = "{$sessionId}:{$agendaItemId}:{$round}";

    expect($key)->toBe('01JABCDEF0123456789012345:01JABCDEF0123456789012346:2');
});

it('exposes session floor cache json for authenticated viewers', function (): void {
    $member = User::factory()->create([
        'email' => 'member-floor-cache@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($member)
        ->getJson(route('sessions.floor.cache', $session))
        ->assertOk()
        ->assertJsonStructure(['cached_at', 'floor', 'document_urls']);
});
