<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table): void {
            $this->signedCopyColumns($table);
        });

        Schema::table('resolutions', function (Blueprint $table): void {
            $this->signedCopyColumns($table);
        });
    }

    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signed_copy_uploaded_by');
            $table->dropColumn([
                'signed_copy_disk',
                'signed_copy_path',
                'signed_copy_filename',
                'signed_copy_mime',
                'signed_copy_size',
                'signed_copy_checksum',
                'signed_copy_scan_status',
                'signed_copy_uploaded_at',
            ]);
        });

        Schema::table('resolutions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signed_copy_uploaded_by');
            $table->dropColumn([
                'signed_copy_disk',
                'signed_copy_path',
                'signed_copy_filename',
                'signed_copy_mime',
                'signed_copy_size',
                'signed_copy_checksum',
                'signed_copy_scan_status',
                'signed_copy_uploaded_at',
            ]);
        });
    }

    private function signedCopyColumns(Blueprint $table): void
    {
        $table->string('signed_copy_disk', 32)->nullable();
        $table->string('signed_copy_path')->nullable();
        $table->string('signed_copy_filename')->nullable();
        $table->string('signed_copy_mime', 80)->nullable();
        $table->unsignedBigInteger('signed_copy_size')->nullable();
        $table->string('signed_copy_checksum', 64)->nullable();
        $table->string('signed_copy_scan_status', 20)->nullable();
        $table->timestampTz('signed_copy_uploaded_at')->nullable();
        $table->foreignUlid('signed_copy_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
    }
};
