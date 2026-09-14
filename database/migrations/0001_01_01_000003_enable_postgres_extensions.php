<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `vector` is not a trusted extension, so on a locked-down cluster a DBA has
 * to create it once. This migration is a no-op when that has already happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    public function down(): void
    {
        // Left in place: dropping the extension would take the embedding
        // columns of any other database object with it.
    }
};
