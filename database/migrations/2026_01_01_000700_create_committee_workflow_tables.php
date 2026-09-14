<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_referrals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('session_id')->nullable()->constrained('sessions')->nullOnDelete();
            $table->foreignUlid('agenda_item_id')->nullable()->constrained('agenda_items')->nullOnDelete();

            $table->string('status', 30)->default('pending')->index();
            $table->boolean('is_primary')->default(true);
            $table->text('instructions')->nullable();
            $table->foreignUlid('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('referred_at');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->timestamps();

            $table->index(['document_id', 'committee_id']);
            $table->index(['committee_id', 'status']);
        });

        Schema::create('committee_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('committee_referral_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('committee_id')->constrained()->cascadeOnDelete();
            // The document being reported on.
            $table->foreignUlid('subject_document_id')->constrained('documents')->cascadeOnDelete();
            // The report itself, once registered as a document.
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->string('report_number', 60)->nullable()->unique();
            $table->string('recommendation', 40)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->text('findings')->nullable();
            $table->text('recommendation_notes')->nullable();
            $table->jsonb('signatories')->nullable();

            $table->foreignUlid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('adopted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_reports');
        Schema::dropIfExists('committee_referrals');
    }
};
