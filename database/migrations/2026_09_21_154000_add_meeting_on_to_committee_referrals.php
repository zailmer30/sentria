<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_referrals', function (Blueprint $table) {
            $table->date('meeting_on')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('committee_referrals', function (Blueprint $table) {
            $table->dropColumn('meeting_on');
        });
    }
};
