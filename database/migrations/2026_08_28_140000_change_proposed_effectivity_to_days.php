<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documents', 'proposed_effectivity')) {
            return;
        }

        $type = Schema::getColumnType('documents', 'proposed_effectivity');

        if (! in_array($type, ['string', 'varchar', 'text', 'character varying'], true)) {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE documents
            ALTER COLUMN proposed_effectivity TYPE smallint
            USING (
                CASE
                    WHEN proposed_effectivity IS NULL OR btrim(proposed_effectivity) = '' THEN NULL
                    WHEN proposed_effectivity ~ '^[0-9]+$' THEN LEAST(proposed_effectivity::integer, 32767)
                    WHEN substring(proposed_effectivity FROM '[0-9]+') IS NOT NULL
                        THEN LEAST(substring(proposed_effectivity FROM '[0-9]+')::integer, 32767)
                    ELSE 10
                END
            )
        SQL);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('documents', 'proposed_effectivity')) {
            return;
        }

        $type = Schema::getColumnType('documents', 'proposed_effectivity');

        if (in_array($type, ['string', 'varchar', 'text'], true)) {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE documents
            ALTER COLUMN proposed_effectivity TYPE varchar(255)
            USING (
                CASE
                    WHEN proposed_effectivity IS NULL THEN NULL
                    ELSE proposed_effectivity::text || ' days after posting'
                END
            )
        SQL);
    }
};
