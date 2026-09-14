<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'sentria:backup';

    protected $description = 'Create a versioned backup of the database, documents, and configuration snapshot';

    public function handle(BackupService $backup): int
    {
        $this->info('Creating Sentria backup…');

        $result = $backup->create();

        $this->info('Backup created at: '.$result['path']);
        $this->line('Manifest size: '.$this->formatBytes((int) ($result['manifest']['size_bytes'] ?? 0)));

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
