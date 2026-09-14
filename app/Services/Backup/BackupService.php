<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class BackupService
{
    public const LAST_BACKUP_CACHE_KEY = 'sentria.backup.last';

    public const LAST_RESTORE_TEST_CACHE_KEY = 'sentria.backup.last_restore_test';

    /**
     * @return array{
     *     path: string,
     *     timestamp: string,
     *     manifest: array<string, mixed>
     * }
     */
    public function create(): array
    {
        $timestamp = now()->format('Y-m-d_His');
        $backupRoot = $this->backupRoot();
        $target = $backupRoot.'/'.$timestamp;

        File::ensureDirectoryExists($target.'/db');
        File::ensureDirectoryExists($target.'/documents');
        File::ensureDirectoryExists($target.'/config');

        $components = [];

        $dbPath = $this->dumpDatabase($target.'/db');
        $components['database'] = [
            'path' => str_replace($target.'/', '', $dbPath),
            'size_bytes' => File::size($dbPath),
            'driver' => (string) config('database.default'),
        ];

        $documentsPath = $this->copyDocuments($target.'/documents');
        $components['documents'] = [
            'path' => 'documents',
            'size_bytes' => $this->directorySize($documentsPath),
            'file_count' => $this->countFiles($documentsPath),
        ];

        $configPath = $this->snapshotConfig($target.'/config');
        $components['config'] = [
            'path' => 'config',
            'size_bytes' => File::size($configPath),
        ];

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'app_version' => (string) config('app.version', '1.0.0'),
            'environment' => (string) config('app.env'),
            'components' => $components,
            'size_bytes' => $this->directorySize($target),
        ];

        File::put($target.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        Cache::forever(self::LAST_BACKUP_CACHE_KEY, [
            'path' => $target,
            'timestamp' => $timestamp,
            'created_at' => $manifest['created_at'],
            'size_bytes' => $manifest['size_bytes'],
            'status' => 'success',
        ]);

        return [
            'path' => $target,
            'timestamp' => $timestamp,
            'manifest' => $manifest,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestStatus(): ?array
    {
        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get(self::LAST_BACKUP_CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        $latest = $this->findLatestBackupDirectory();

        if ($latest === null) {
            return null;
        }

        $manifestPath = $latest.'/manifest.json';
        if (! File::isFile($manifestPath)) {
            return null;
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        return [
            'path' => $latest,
            'timestamp' => basename($latest),
            'created_at' => $manifest['created_at'] ?? null,
            'size_bytes' => $manifest['size_bytes'] ?? $this->directorySize($latest),
            'status' => 'success',
        ];
    }

    public function lastRestoreTestAt(): ?Carbon
    {
        $value = Cache::get(self::LAST_RESTORE_TEST_CACHE_KEY);

        return is_string($value) ? Carbon::parse($value) : null;
    }

    public function markRestoreTestSuccessful(): void
    {
        Cache::forever(self::LAST_RESTORE_TEST_CACHE_KEY, now()->toIso8601String());
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(string $path, bool $force = false, bool $restoreDatabase = true): array
    {
        $resolved = realpath($path);
        if ($resolved === false || ! File::isDirectory($resolved)) {
            throw new RuntimeException("Backup directory not found: {$path}");
        }

        $manifestPath = $resolved.'/manifest.json';
        if (! File::isFile($manifestPath)) {
            throw new RuntimeException('manifest.json is missing from the backup directory.');
        }

        if (! $force) {
            throw new RuntimeException('Restore is destructive. Re-run with --force to confirm.');
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $restored = [
            'documents' => $this->restoreDocuments($resolved.'/documents'),
            'database' => false,
        ];

        if ($restoreDatabase) {
            $restored['database'] = $this->restoreDatabase($resolved.'/db');
        }

        $this->markRestoreTestSuccessful();

        return [
            'manifest' => $manifest,
            'restored' => $restored,
        ];
    }

    /**
     * Verify backup artifact integrity without applying a full database restore.
     *
     * @return array<string, mixed>
     */
    public function verifyArtifacts(string $path): array
    {
        $resolved = realpath($path);
        if ($resolved === false || ! File::isDirectory($resolved)) {
            throw new RuntimeException("Backup directory not found: {$path}");
        }

        $manifestPath = $resolved.'/manifest.json';
        if (! File::isFile($manifestPath)) {
            throw new RuntimeException('manifest.json is missing from the backup directory.');
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $dbFiles = File::glob($resolved.'/db/*') ?: [];
        $documentRoot = $resolved.'/documents';

        return [
            'manifest' => $manifest,
            'database_files' => count($dbFiles),
            'document_files' => File::isDirectory($documentRoot) ? $this->countFiles($documentRoot) : 0,
            'size_bytes' => $this->directorySize($resolved),
        ];
    }

    public function backupRoot(): string
    {
        return storage_path('app/backups');
    }

    private function dumpDatabase(string $directory): string
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($driver === 'pgsql') {
            $target = $directory.'/database.sql';

            if ($this->runPgDump($target)) {
                return $target;
            }
        }

        return $this->dumpDatabaseViaPhp($directory, $driver);
    }

    private function runPgDump(string $target): bool
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}");

        if (! is_array($config)) {
            return false;
        }

        $binary = trim((string) shell_exec('command -v pg_dump'));
        if ($binary === '') {
            return false;
        }

        $command = [
            $binary,
            '--no-owner',
            '--no-acl',
            '--format=plain',
            '--file='.$target,
        ];

        $host = $config['host'] ?? null;
        $port = $config['port'] ?? null;
        $database = $config['database'] ?? null;
        $username = $config['username'] ?? null;

        if (is_string($host) && $host !== '') {
            $command[] = '--host='.$host;
        }

        if (is_string($port) && $port !== '') {
            $command[] = '--port='.$port;
        }

        if (is_string($username) && $username !== '') {
            $command[] = '--username='.$username;
        }

        if (is_string($database) && $database !== '') {
            $command[] = $database;
        }

        $environment = [];
        $password = $config['password'] ?? null;
        if (is_string($password) && $password !== '') {
            $environment['PGPASSWORD'] = $password;
        }

        $result = Process::timeout(600)->env($environment)->run($command);

        return $result->successful() && File::isFile($target) && File::size($target) > 0;
    }

    private function dumpDatabaseViaPhp(string $directory, string $driver): string
    {
        $target = $directory.'/database.sql';
        $handle = fopen($target, 'w');

        if ($handle === false) {
            throw new RuntimeException('Unable to create SQL dump file.');
        }

        fwrite($handle, '-- Sentria backup generated at '.now()->toIso8601String()."\n");
        fwrite($handle, "-- Driver: {$driver}\n\n");

        $tables = DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename");

        foreach ($tables as $tableRow) {
            $table = $tableRow->tablename ?? null;
            if (! is_string($table)) {
                continue;
            }

            fwrite($handle, "\n-- Table: {$table}\n");

            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $columns = array_keys((array) $row);
                $values = array_map(function ($value): string {
                    if ($value === null) {
                        return 'NULL';
                    }

                    if (is_bool($value)) {
                        return $value ? 'true' : 'false';
                    }

                    if (is_int($value) || is_float($value)) {
                        return (string) $value;
                    }

                    return "'".str_replace("'", "''", (string) $value)."'";
                }, array_values((array) $row));

                $columnList = implode(', ', array_map(fn (string $column): string => "\"{$column}\"", $columns));
                $valueList = implode(', ', $values);
                fwrite($handle, "INSERT INTO \"{$table}\" ({$columnList}) VALUES ({$valueList});\n");
            }
        }

        fclose($handle);

        return $target;
    }

    private function restoreDatabase(string $directory): bool
    {
        $sqlFile = collect(File::glob($directory.'/*'))
            ->first(fn (string $path): bool => str_ends_with($path, '.sql'));

        if ($sqlFile === null || ! File::isFile($sqlFile)) {
            return false;
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($driver === 'pgsql') {
            $binary = trim((string) shell_exec('command -v psql'));
            if ($binary !== '') {
                $config = config("database.connections.{$connection}");
                if (! is_array($config)) {
                    return false;
                }

                $command = [$binary, '--file='.$sqlFile];
                $host = $config['host'] ?? null;
                $port = $config['port'] ?? null;
                $database = $config['database'] ?? null;
                $username = $config['username'] ?? null;

                if (is_string($host) && $host !== '') {
                    $command[] = '--host='.$host;
                }

                if (is_string($port) && $port !== '') {
                    $command[] = '--port='.$port;
                }

                if (is_string($username) && $username !== '') {
                    $command[] = '--username='.$username;
                }

                if (is_string($database) && $database !== '') {
                    $command[] = $database;
                }

                $environment = [];
                $password = $config['password'] ?? null;
                if (is_string($password) && $password !== '') {
                    $environment['PGPASSWORD'] = $password;
                }

                return Process::timeout(600)->env($environment)->run($command)->successful();
            }
        }

        // Full SQL restore requires psql; PHP-generated dumps are verified via artifact tests.
        return false;
    }

    private function copyDocuments(string $target): string
    {
        $source = storage_path('app/private');

        if (File::isDirectory($source)) {
            File::copyDirectory($source, $target);
        } else {
            File::ensureDirectoryExists($target);
        }

        return $target;
    }

    /**
     * @return array{files: int, bytes: int}
     */
    private function restoreDocuments(string $source): array
    {
        $destination = storage_path('app/private');
        File::ensureDirectoryExists($destination);

        if (! File::isDirectory($source)) {
            return ['files' => 0, 'bytes' => 0];
        }

        File::copyDirectory($source, $destination);

        return [
            'files' => $this->countFiles($destination),
            'bytes' => $this->directorySize($destination),
        ];
    }

    private function snapshotConfig(string $directory): string
    {
        $target = $directory.'/env.example.snapshot';
        $example = base_path('.env.example');
        $contents = File::isFile($example)
            ? File::get($example)
            : "# .env.example not found\n";

        File::put($target, $contents);

        return $target;
    }

    private function findLatestBackupDirectory(): ?string
    {
        $root = $this->backupRoot();
        if (! File::isDirectory($root)) {
            return null;
        }

        $directories = collect(File::directories($root))
            ->sortDesc()
            ->values();

        return $directories->first();
    }

    private function directorySize(string $path): int
    {
        if (! File::isDirectory($path)) {
            return File::isFile($path) ? File::size($path) : 0;
        }

        return collect(File::allFiles($path))->sum(fn ($file) => $file->getSize());
    }

    private function countFiles(string $path): int
    {
        if (! File::isDirectory($path)) {
            return 0;
        }

        return count(File::allFiles($path));
    }
}
