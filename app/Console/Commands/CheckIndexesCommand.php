<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckIndexesCommand extends Command
{
    protected $signature = 'sentria:check-indexes';

    protected $description = 'Verify critical PostgreSQL indexes including document_embeddings HNSW';

    public function handle(): int
    {
        $driver = (string) config('database.connections.'.config('database.default').'.driver');

        if ($driver !== 'pgsql') {
            $this->warn('Index check is intended for PostgreSQL deployments.');

            return self::SUCCESS;
        }

        $indexes = DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'document_embeddings'");

        $hasHnsw = collect($indexes)->contains(
            fn ($index): bool => str_contains(strtolower((string) $index->indexdef), 'hnsw'),
        );

        if ($hasHnsw) {
            $this->info('document_embeddings HNSW index present.');
        } else {
            $this->error('document_embeddings HNSW index missing.');

            return self::FAILURE;
        }

        foreach ($indexes as $index) {
            $this->line('- '.$index->indexname);
        }

        return self::SUCCESS;
    }
}
