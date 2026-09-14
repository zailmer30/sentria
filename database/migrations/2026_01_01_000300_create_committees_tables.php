<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 30)->nullable()->unique();
            $table->string('type', 30)->default('standing');
            $table->text('mandate')->nullable();
            $table->text('description')->nullable();
            $table->date('established_on')->nullable();
            $table->date('dissolved_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'type']);
        });

        Schema::create('committee_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('position', 30)->default('member');
            $table->date('appointed_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['committee_id', 'user_id']);
            $table->index(['committee_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_members');
        Schema::dropIfExists('committees');
    }
};
