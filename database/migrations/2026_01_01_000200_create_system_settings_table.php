<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('group')->default('general')->index();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('string');
            $table->jsonb('value')->nullable();
            $table->jsonb('options')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
