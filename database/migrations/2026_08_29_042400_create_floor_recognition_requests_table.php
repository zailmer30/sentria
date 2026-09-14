<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_recognition_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('agenda_item_id')->nullable()->constrained('agenda_items')->nullOnDelete();

            $table->string('status', 20)->default('pending')->index();
            $table->timestampTz('raised_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['session_id', 'status']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX floor_recognition_one_pending_per_member
            ON floor_recognition_requests (session_id, user_id)
            WHERE status = 'pending'
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX floor_recognition_one_recognized
            ON floor_recognition_requests (session_id)
            WHERE status = 'recognized' AND resolved_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_recognition_requests');
    }
};
