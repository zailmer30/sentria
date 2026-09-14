<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Database-layer enforcement for the two tables that must never be rewritten.
 * The application layer refuses updates too; this is the backstop for direct
 * SQL access.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION sentria_refuse_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION
                    'Table % is append-only; % is not permitted.', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'restrict_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        foreach (['audit_logs', 'votes'] as $table) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$table}_append_only
                BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION sentria_refuse_mutation();
            SQL);
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'votes'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_append_only ON {$table}");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS sentria_refuse_mutation()');
    }
};
