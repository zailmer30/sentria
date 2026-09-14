<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Members may change their ballot while the round is still open. The original
 * row stays (votes remain append-only); a later row is the counted choice.
 * The one-ballot unique constraint would block that second insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE votes DROP CONSTRAINT IF EXISTS votes_one_ballot_per_member_per_round');
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE votes
            ADD CONSTRAINT votes_one_ballot_per_member_per_round
            UNIQUE NULLS NOT DISTINCT (session_id, agenda_item_id, voting_round, user_id)
        SQL);
    }
};
