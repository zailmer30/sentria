<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name')->nullable();
            $table->string('direct_pair_key', 53)->nullable();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'direct_pair_key']);
            $table->index(['session_id', 'last_message_at']);
            $table->index(['session_id', 'type']);
        });

        Schema::create('session_conversation_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('session_conversations')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('joined_at');
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('session_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('session_conversations')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_messages');
        Schema::dropIfExists('session_conversation_participants');
        Schema::dropIfExists('session_conversations');
    }
};
