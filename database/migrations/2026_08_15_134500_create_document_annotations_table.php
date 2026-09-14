<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Owner-only PDF marks. No role, grant, export, or AI retrieval path
        // may widen access to another user's annotations.
        Schema::create('document_annotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('document_version_id')->constrained()->cascadeOnDelete();
            $table->jsonb('payload');
            $table->timestamps();

            $table->unique(['user_id', 'document_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_annotations');
    }
};
