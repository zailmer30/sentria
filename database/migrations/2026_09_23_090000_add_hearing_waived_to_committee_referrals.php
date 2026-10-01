<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_referrals', function (Blueprint $table) {
            $table->boolean('hearing_waived')->default(false)->after('meeting_on');
        });
    }

    public function down(): void
    {
        Schema::table('committee_referrals', function (Blueprint $table) {
            $table->dropColumn('hearing_waived');
        });
    }
};
