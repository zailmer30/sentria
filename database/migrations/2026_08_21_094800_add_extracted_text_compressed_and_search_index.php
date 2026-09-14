<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->binary('extracted_text_compressed')->nullable()->after('extracted_text');
            $table->text('ocr_error')->nullable()->after('ocr_status');
            $table->string('extraction_method', 20)->nullable()->after('ocr_error');
        });

        Schema::create('document_search_indexes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->text('excerpt')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE document_search_indexes ADD COLUMN search_vector tsvector');
        DB::statement('CREATE INDEX document_search_indexes_search_vector_index ON document_search_indexes USING GIN (search_vector)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_search_indexes');

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn(['extracted_text_compressed', 'ocr_error', 'extraction_method']);
        });
    }
};
