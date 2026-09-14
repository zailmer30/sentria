<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // owen-it/laravel-auditing model diffs. ULID variant of the package stub.
        Schema::create('audits', function (Blueprint $table) {
            $morphPrefix = config('audit.user.morph_prefix', 'user');

            $table->ulid('id')->primary();
            $table->string($morphPrefix.'_type')->nullable();
            $table->ulid($morphPrefix.'_id')->nullable();
            $table->string('event');
            $table->ulidMorphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
        });

        // Monotonic ordering for the hash chain, independent of clock skew.
        DB::statement('CREATE SEQUENCE audit_logs_sequence_seq AS BIGINT START WITH 1 INCREMENT BY 1');

        // Append-only legislative audit trail. Every row carries the hash of
        // its own content plus the previous row's hash, so any tampering
        // breaks the chain. Verified by `php artisan audit:verify-chain`.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->bigInteger('sequence')->unique()->default(DB::raw("nextval('audit_logs_sequence_seq')"));

            $table->string('event')->index();
            $table->string('category', 40)->default('general')->index();
            $table->nullableUlidMorphs('auditable');

            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable();
            $table->string('actor_role')->nullable();
            $table->boolean('is_ai_actor')->default(false);

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('route')->nullable();
            $table->string('method', 10)->nullable();

            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('context')->nullable();
            $table->text('message')->nullable();

            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->unique();

            $table->timestampTz('occurred_at')->index();
            $table->timestamp('created_at')->nullable();
        });

        DB::statement('ALTER SEQUENCE audit_logs_sequence_seq OWNED BY audit_logs.sequence');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        DB::statement('DROP SEQUENCE IF EXISTS audit_logs_sequence_seq');
        Schema::dropIfExists('audits');
    }
};
