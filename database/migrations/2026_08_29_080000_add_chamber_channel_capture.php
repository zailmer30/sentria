<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamber_channels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedSmallInteger('channel_index')->unique();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'channel_index']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->boolean('recording_enabled')->default(true);
        });

        Schema::table('transcripts', function (Blueprint $table) {
            $table->jsonb('channel_map_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transcripts', function (Blueprint $table) {
            $table->dropColumn('channel_map_snapshot');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('recording_enabled');
        });

        Schema::dropIfExists('chamber_channels');
    }
};
