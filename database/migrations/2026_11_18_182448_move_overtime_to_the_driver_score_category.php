<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Overtime counts the same for every team: from the pace score category
 * (weighed by team form) to driver (×1). Decided by the owner on
 * 18 November 2026. Rebuild the OOFscore of the open seasons afterwards
 * (oof:oofscore), as this bypasses the model's own rebuild.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('achievements')->where('key', 'overtime')->where('score_category', 'pace')->update(['score_category' => 'driver', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('achievements')->where('key', 'overtime')->where('score_category', 'driver')->update(['score_category' => 'pace', 'updated_at' => now()]);
    }
};
