<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn([
                'approved_on',
                'vetoed_on',
                'veto_overridden_on',
                'publication_date',
                'publication_medium',
                'sp_submitted_on',
                'sp_reviewed_on',
                'sp_result',
            ]);
        });

        Schema::table('resolutions', function (Blueprint $table) {
            $table->dropColumn([
                'lce_sp_required',
                'sp_submitted_on',
                'sp_reviewed_on',
                'sp_result',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->date('approved_on')->nullable();
            $table->date('vetoed_on')->nullable();
            $table->date('veto_overridden_on')->nullable();
            $table->date('publication_date')->nullable();
            $table->string('publication_medium')->nullable();
            $table->date('sp_submitted_on')->nullable();
            $table->date('sp_reviewed_on')->nullable();
            $table->string('sp_result', 20)->nullable();
        });

        Schema::table('resolutions', function (Blueprint $table) {
            $table->boolean('lce_sp_required')->default(false);
            $table->date('sp_submitted_on')->nullable();
            $table->date('sp_reviewed_on')->nullable();
            $table->string('sp_result', 20)->nullable();
        });
    }
};
