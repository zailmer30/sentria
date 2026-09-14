<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneChamberAudioCommand extends Command
{
    protected $signature = 'sentria:prune-chamber-audio';

    protected $description = 'Delete raw per-seat chamber audio older than the configured retention period. Transcript text is kept.';

    public function handle(): int
    {
        $days = max(1, (int) config('sentria.chamber.audio_retention_days', 30));
        $cutoff = now()->subDays($days)->getTimestamp();
        $disk = Storage::disk('local');
        $removed = 0;

        foreach ($disk->allFiles('sessions') as $path) {
            if (! str_contains($path, '/chamber/')) {
                continue;
            }

            $modified = $disk->lastModified($path);

            if ($modified !== false && $modified < $cutoff) {
                $disk->delete($path);
                $removed++;
            }
        }

        $this->info("Pruned {$removed} chamber audio file(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
