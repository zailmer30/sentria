<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_embeddings', function (Blueprint $table): void {
            $table->string('section_heading')->nullable()->after('page_number');
            $table->string('section_number')->nullable()->after('section_heading');
            $table->unsignedInteger('char_start')->nullable()->after('section_number');
            $table->unsignedInteger('char_end')->nullable()->after('char_start');
            $table->char('content_hash', 64)->nullable()->after('char_end');
        });

        Schema::table('document_versions', function (Blueprint $table): void {
            $table->string('processing_status', 20)->default('pending')->after('text_extracted_at');
            $table->text('processing_error')->nullable()->after('processing_status');
            $table->timestampTz('processed_at')->nullable()->after('processing_error');
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table): void {
            $table->dropColumn(['processing_status', 'processing_error', 'processed_at']);
        });

        Schema::table('document_embeddings', function (Blueprint $table): void {
            $table->dropColumn([
                'section_heading',
                'section_number',
                'char_start',
                'char_end',
                'content_hash',
            ]);
        });
    }
};
