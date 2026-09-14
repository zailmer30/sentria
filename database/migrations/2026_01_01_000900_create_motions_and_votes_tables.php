<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignUlid('agenda_item_id')->nullable()->constrained('agenda_items')->nullOnDelete();
            $table->ulid('parent_motion_id')->nullable();

            $table->string('type', 30)->default('main');
            $table->text('text');
            $table->string('status', 30)->default('proposed')->index();

            $table->foreignUlid('moved_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('seconded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('moved_at');
            $table->timestampTz('seconded_at')->nullable();
            $table->timestampTz('disposed_at')->nullable();

            $table->boolean('requires_vote')->default(true);
            $table->unsignedSmallInteger('voting_round')->nullable();
            $table->text('disposition_notes')->nullable();

            $table->timestamps();

            $table->index(['session_id', 'status']);
        });

        // Self-referencing key is added after the table exists so the primary
        // key it points at is already in place.
        Schema::table('motions', function (Blueprint $table) {
            $table->foreign('parent_motion_id')->references('id')->on('motions')->nullOnDelete();
        });

        // Ballots are immutable. A correction is a new voting round, never an
        // update. Enforced by trigger in a later migration.
        Schema::create('votes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignUlid('agenda_item_id')->nullable()->constrained('agenda_items')->nullOnDelete();
            $table->foreignUlid('motion_id')->nullable()->constrained('motions')->nullOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('voting_round')->default(1);
            $table->string('choice', 20);
            $table->string('method', 30)->default('electronic');
            $table->timestampTz('cast_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->nullable();

            $table->index(['session_id', 'agenda_item_id', 'voting_round']);
        });

        // Idempotent submission: one ballot per member per round. NULLS NOT
        // DISTINCT so a null agenda_item_id still collides.
        DB::statement(<<<'SQL'
            ALTER TABLE votes
            ADD CONSTRAINT votes_one_ballot_per_member_per_round
            UNIQUE NULLS NOT DISTINCT (session_id, agenda_item_id, voting_round, user_id)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE votes
            ADD CONSTRAINT votes_choice_check
            CHECK (choice IN ('yes', 'no', 'abstain', 'inhibit'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('motions');
    }
};
