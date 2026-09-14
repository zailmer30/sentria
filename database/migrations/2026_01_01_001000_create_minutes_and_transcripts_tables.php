<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minutes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->unique()->constrained('sessions')->cascadeOnDelete();
            $table->string('status')->index();
            $table->unsignedInteger('revision')->default(1);

            // The AI draft is kept separate from the official content so the
            // human-edited record is never overwritten by a regeneration.
            $table->longText('ai_draft')->nullable();
            $table->timestampTz('ai_generated_at')->nullable();
            $table->string('ai_model')->nullable();
            $table->jsonb('ai_metadata')->nullable();

            $table->longText('content')->nullable();
            $table->longText('content_html')->nullable();

            $table->foreignUlid('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transcripts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignUlid('agenda_item_id')->nullable()->constrained('agenda_items')->nullOnDelete();

            $table->string('source', 30)->default('live_stt');
            $table->string('status', 30)->default('pending')->index();
            $table->string('language', 5)->default('en');

            $table->string('disk', 40)->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->decimal('average_confidence', 5, 4)->nullable();

            $table->longText('full_text')->nullable();
            $table->jsonb('segments')->nullable();

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
        Schema::dropIfExists('minutes');
    }
};
