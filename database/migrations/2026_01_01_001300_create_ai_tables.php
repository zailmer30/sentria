<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->nullableUlidMorphs('context');
            $table->string('model')->nullable();
            $table->string('system_prompt_version', 40)->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestampTz('last_message_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('finish_reason', 40)->nullable();
            $table->jsonb('tool_calls')->nullable();
            // Set when the guardrail layer detects prompt injection in
            // retrieved document content.
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'created_at']);
        });

        // Every AI claim is traceable to a source the user was already
        // authorized to read.
        Schema::create('ai_citations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_message_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('document_embedding_id')->nullable();
            $table->unsignedSmallInteger('rank')->default(1);
            $table->decimal('similarity', 8, 6)->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->text('quote')->nullable();
            $table->timestamps();

            $table->index(['ai_message_id', 'rank']);
        });

        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('document_version_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('chunk_text');
            $table->unsignedInteger('token_count')->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->string('model');
            // Denormalized so the authorization filter can run inside the
            // retrieval query instead of post-filtering results.
            $table->string('confidentiality', 20)->default('internal')->index();
            $table->boolean('is_public')->default(false)->index();
            $table->vector('embedding', 1536);
            $table->timestamps();

            $table->unique(['document_version_id', 'chunk_index']);
            $table->index('document_id');
        });

        DB::statement(<<<'SQL'
            CREATE INDEX document_embeddings_embedding_index
            ON document_embeddings
            USING hnsw (embedding vector_cosine_ops)
        SQL);

        Schema::table('ai_citations', function (Blueprint $table) {
            $table->foreign('document_embedding_id')->references('id')->on('document_embeddings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_citations', function (Blueprint $table) {
            $table->dropForeign(['document_embedding_id']);
        });

        Schema::dropIfExists('document_embeddings');
        Schema::dropIfExists('ai_citations');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
