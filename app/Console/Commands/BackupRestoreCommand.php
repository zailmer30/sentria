<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

class BackupRestoreCommand extends Command
{
    protected $signature = 'sentria:backup-restore {path : Path to a backup directory under storage/app/backups}
                            {--force : Confirm destructive restore}
                            {--documents-only : Restore document files only (skip database)}';

    protected $description = 'Restore Sentria from a backup directory (destructive)';

    public function handle(BackupService $backup): int
    {
        $path = (string) $this->argument('path');

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = storage_path('app/backups/'.trim($path, '/'));
        }

        try {
            $result = $backup->restore(
                $path,
                (bool) $this->option('force'),
                ! (bool) $this->option('documents-only'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Restore completed.');
        $this->line('Documents restored: '.json_encode($result['restored']['documents'] ?? []));
        $this->line('Database restored: '.(($result['restored']['database'] ?? false) ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
