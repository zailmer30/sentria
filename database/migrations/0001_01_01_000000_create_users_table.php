<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('name_suffix', 20)->nullable();
            $table->string('honorific', 40)->nullable();
            $table->string('display_name');

            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            $table->string('employee_number', 60)->nullable()->unique();
            $table->string('position_title')->nullable();
            $table->string('district')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('avatar_path')->nullable();

            $table->string('locale', 5)->default('en');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_seated_member')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('password_changed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_seated_member');
            $table->index('last_name');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Named `http_sessions` so the canonical `sessions` table can model
        // legislative sessions. See config/session.php.
        Schema::create('http_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('http_sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
