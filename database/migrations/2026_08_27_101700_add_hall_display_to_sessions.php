<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('hall_display_stage', 20)->default('item')->after('notes');
            $table->foreignUlid('hall_display_agenda_item_id')
                ->nullable()
                ->after('hall_display_stage')
                ->constrained('agenda_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hall_display_agenda_item_id');
            $table->dropColumn('hall_display_stage');
        });
    }
};
