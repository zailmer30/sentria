<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legislative sessions. HTTP sessions live in `http_sessions`.
        Schema::create('sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_number', 60)->unique();
            $table->string('title');
            $table->string('type', 30)->default('regular');
            $table->string('status')->index();
            $table->unsignedSmallInteger('legislative_year')->nullable()->index();

            $table->timestampTz('scheduled_start_at')->nullable();
            $table->timestampTz('scheduled_end_at')->nullable();
            $table->timestampTz('actual_start_at')->nullable();
            $table->timestampTz('actual_end_at')->nullable();
            $table->timestampTz('agenda_locked_at')->nullable();
            $table->timestampTz('documents_distributed_at')->nullable();
            $table->timestampTz('adjourned_at')->nullable();

            $table->string('venue')->nullable();
            $table->foreignUlid('presiding_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('secretary_id')->nullable()->constrained('users')->nullOnDelete();

            // Snapshot of the configured quorum rule at scheduling time. The UI
            // reports quorum status; a human decides whether to proceed.
            $table->unsignedSmallInteger('seated_member_count')->nullable();
            $table->unsignedSmallInteger('quorum_required')->nullable();
            $table->timestampTz('quorum_declared_at')->nullable();
            $table->foreignUlid('quorum_declared_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_public')->default(true);
            $table->string('livestream_url')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_start_at']);
        });

        Schema::create('session_attendance', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('absent');
            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('checked_out_at')->nullable();
            $table->string('check_in_method', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_attendance');
        Schema::dropIfExists('sessions');
    }
};
