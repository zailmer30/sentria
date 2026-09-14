<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();

            $table->unsignedInteger('position');
            $table->string('item_number', 30)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 40)->index();
            $table->string('status', 30)->default('pending')->index();

            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignUlid('committee_id')->nullable()->constrained('committees')->nullOnDelete();
            $table->foreignUlid('presented_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedSmallInteger('time_allotment_minutes')->nullable();
            $table->unsignedSmallInteger('reading_number')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->boolean('requires_vote')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['session_id', 'position']);
        });

        // Self-referencing key is added after the table exists so the primary
        // key it points at is already in place.
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('agenda_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_items');
    }
};
