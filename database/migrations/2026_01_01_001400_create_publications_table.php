<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A document reaches the public portal only through an explicit
        // publication record in the `published` state.
        Schema::create('publications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->index();

            $table->string('public_slug')->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->jsonb('categories')->nullable();

            $table->boolean('redaction_applied')->default(false);
            $table->text('redaction_notes')->nullable();
            $table->text('review_notes')->nullable();

            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignUlid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('unpublished_at')->nullable();

            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('download_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
