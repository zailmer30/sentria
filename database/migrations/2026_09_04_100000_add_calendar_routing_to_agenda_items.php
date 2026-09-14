<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->timestampTz('postponed_at')->nullable()->after('completed_at');
            $table->string('postponed_from_category', 40)->nullable()->after('postponed_at');
            $table->ulid('postponed_from_parent_id')->nullable()->after('postponed_from_category');
            $table->foreignUlid('carried_to_session_id')->nullable()->after('postponed_from_parent_id')->constrained('sessions')->nullOnDelete();
            $table->ulid('carried_to_agenda_item_id')->nullable()->after('carried_to_session_id');
        });

        Schema::table('agenda_items', function (Blueprint $table) {
            $table->foreign('postponed_from_parent_id')->references('id')->on('agenda_items')->nullOnDelete();
            $table->foreign('carried_to_agenda_item_id')->references('id')->on('agenda_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropForeign(['carried_to_session_id']);
            $table->dropForeign(['postponed_from_parent_id']);
            $table->dropForeign(['carried_to_agenda_item_id']);
            $table->dropColumn([
                'postponed_at',
                'postponed_from_category',
                'postponed_from_parent_id',
                'carried_to_session_id',
                'carried_to_agenda_item_id',
            ]);
        });
    }
};
