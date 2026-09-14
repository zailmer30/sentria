<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table): void {
            $table->timestampTz('imported_at')->nullable()->after('sp_result');
            $table->foreignUlid('imported_by')->nullable()->after('imported_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('resolutions', function (Blueprint $table): void {
            $table->timestampTz('imported_at')->nullable()->after('sp_result');
            $table->foreignUlid('imported_by')->nullable()->after('imported_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('legislation_import_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('kind', 20)->index();
            $table->string('status', 20)->default('previewed')->index();
            $table->foreignUlid('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('csv_path');
            $table->string('csv_filename');
            $table->string('zip_path');
            $table->string('zip_filename');
            $table->jsonb('preview')->nullable();
            $table->jsonb('result')->nullable();
            $table->timestampTz('committed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislation_import_batches');

        Schema::table('ordinances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('imported_by');
            $table->dropColumn('imported_at');
        });

        Schema::table('resolutions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('imported_by');
            $table->dropColumn('imported_at');
        });
    }
};
