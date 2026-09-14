<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'session_completed' => 'session-completed',
            'final' => 'final-minutes',
            'archived' => 'archive',
        ];

        foreach ($map as $from => $to) {
            DB::table('minutes')->where('status', $from)->update(['status' => $to]);
        }
    }

    public function down(): void
    {
        $map = [
            'session-completed' => 'session_completed',
            'final-minutes' => 'final',
            'archive' => 'archived',
        ];

        foreach ($map as $from => $to) {
            DB::table('minutes')->where('status', $from)->update(['status' => $to]);
        }
    }
};
