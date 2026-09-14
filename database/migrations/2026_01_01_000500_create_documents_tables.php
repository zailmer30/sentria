<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference_number', 80)->nullable()->unique();
            $table->string('tracking_number', 80)->nullable()->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('abstract')->nullable();

            $table->string('document_type', 40)->index();
            $table->string('status')->index();
            $table->string('confidentiality', 20)->default('internal')->index();
            $table->string('origin', 30)->default('member');
            $table->string('language', 5)->default('en');

            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_author')->nullable();
            $table->foreignUlid('session_id')->nullable()->constrained('sessions')->nullOnDelete();
            $table->foreignUlid('committee_id')->nullable()->constrained('committees')->nullOnDelete();

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('registered_at')->nullable();
            $table->foreignUlid('registered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_public')->default(false)->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->jsonb('tags')->nullable();
            $table->unsignedInteger('version_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type', 'status']);
            $table->index(['is_public', 'published_at']);
        });

        // PostgreSQL full-text search is the initial search backend.
        DB::statement(<<<'SQL'
            ALTER TABLE documents
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(reference_number, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(abstract, '')), 'B')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX documents_search_vector_index ON documents USING GIN (search_vector)');

        Schema::create('document_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->boolean('is_current')->default(false);

            $table->string('disk', 40)->default('local');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('file_size');
            $table->char('checksum_sha256', 64)->index();
            $table->unsignedInteger('page_count')->nullable();

            $table->string('scan_status', 20)->default('pending');
            $table->timestampTz('scanned_at')->nullable();
            $table->string('scanner')->nullable();
            $table->text('scan_result')->nullable();

            $table->string('ocr_status', 20)->default('not_required');
            $table->longText('extracted_text')->nullable();
            $table->timestampTz('text_extracted_at')->nullable();

            $table->text('change_summary')->nullable();
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['document_id', 'is_current']);
        });

        Schema::create('document_metadata', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->jsonb('value_json')->nullable();
            // AI-derived metadata is always attributed so reviewers can verify it.
            $table->string('source', 20)->default('manual');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'key']);
        });

        Schema::create('document_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('committee_id')->nullable()->constrained('committees')->cascadeOnDelete();
            $table->string('ability', 30)->default('view');
            $table->foreignUlid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('granted_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'ability']);
            $table->index(['user_id', 'ability']);
        });

        // Exactly one grantee per row.
        DB::statement(<<<'SQL'
            ALTER TABLE document_grants
            ADD CONSTRAINT document_grants_single_grantee_check CHECK (
                (CASE WHEN user_id IS NULL THEN 0 ELSE 1 END) +
                (CASE WHEN role_id IS NULL THEN 0 ELSE 1 END) +
                (CASE WHEN committee_id IS NULL THEN 0 ELSE 1 END) = 1
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_grants');
        Schema::dropIfExists('document_metadata');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
