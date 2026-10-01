<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minutes_corrections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignUlid('agenda_item_id')->constrained('agenda_items')->cascadeOnDelete();
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('as_written');
            $table->text('should_read');
            $table->unsignedInteger('page_number')->nullable();
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['session_id', 'agenda_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minutes_corrections');
    }
};
