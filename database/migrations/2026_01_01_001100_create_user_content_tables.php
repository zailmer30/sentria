<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Private notes are owner-only for their entire lifetime. No grant,
        // role, or AI retrieval path may widen access.
        Schema::create('private_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('notable');
            $table->text('body');
            $table->unsignedInteger('page_number')->nullable();
            $table->jsonb('anchor')->nullable();
            $table->string('color', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'notable_type', 'notable_id']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('bookmarkable');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'bookmarkable_type', 'bookmarkable_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type');
            $table->ulidMorphs('notifiable');
            $table->text('data');
            $table->string('category', 40)->nullable()->index();
            $table->string('priority', 20)->default('normal');
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('private_notes');
    }
};
