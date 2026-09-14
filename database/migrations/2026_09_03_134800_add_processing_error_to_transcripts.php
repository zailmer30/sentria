<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcripts', function (Blueprint $table): void {
            $table->text('processing_error')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('transcripts', function (Blueprint $table): void {
            $table->dropColumn('processing_error');
        });
    }
};
