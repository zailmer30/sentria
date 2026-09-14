<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->timestampTz('voting_opened_at')->nullable()->after('voting_open_at');
            $table->timestampTz('voting_closed_at')->nullable()->after('voting_opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropColumn(['voting_opened_at', 'voting_closed_at']);
        });
    }
};
