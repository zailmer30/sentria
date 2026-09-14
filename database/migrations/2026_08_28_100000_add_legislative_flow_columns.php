<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('enacting_clause')->nullable()->after('abstract');
            $table->unsignedSmallInteger('proposed_effectivity')->nullable()->after('enacting_clause');
            $table->text('explanatory_note')->nullable()->after('proposed_effectivity');
            $table->unsignedTinyInteger('current_reading')->nullable()->after('explanatory_note');
            $table->timestampTz('sealed_at')->nullable()->after('archived_at');
            $table->foreignUlid('sealed_by')->nullable()->after('sealed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('ordinances', function (Blueprint $table) {
            $table->date('sp_submitted_on')->nullable()->after('publication_medium');
            $table->date('sp_reviewed_on')->nullable()->after('sp_submitted_on');
            $table->string('sp_result', 20)->nullable()->after('sp_reviewed_on');
        });

        Schema::table('resolutions', function (Blueprint $table) {
            $table->boolean('lce_sp_required')->default(false)->after('transmitted_to');
            $table->date('sp_submitted_on')->nullable()->after('lce_sp_required');
            $table->date('sp_reviewed_on')->nullable()->after('sp_submitted_on');
            $table->string('sp_result', 20)->nullable()->after('sp_reviewed_on');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sealed_by');
            $table->dropColumn([
                'enacting_clause',
                'proposed_effectivity',
                'explanatory_note',
                'current_reading',
                'sealed_at',
            ]);
        });

        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn(['sp_submitted_on', 'sp_reviewed_on', 'sp_result']);
        });

        Schema::table('resolutions', function (Blueprint $table) {
            $table->dropColumn(['lce_sp_required', 'sp_submitted_on', 'sp_reviewed_on', 'sp_result']);
        });
    }
};
