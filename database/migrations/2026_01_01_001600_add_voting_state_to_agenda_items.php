<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('voting_round')->default(0)->after('requires_vote');
            $table->timestampTz('voting_open_at')->nullable()->after('voting_round');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropColumn(['voting_round', 'voting_open_at']);
        });
    }
};
