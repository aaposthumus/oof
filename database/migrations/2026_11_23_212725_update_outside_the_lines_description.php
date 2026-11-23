<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Outside the Lines needs 2 or more lap times deleted for track limits in
 * one session (decided by the owner on 23 November 2026). The description
 * says so where it still equals the seeded text; a CMS edit is kept.
 */
return new class extends Migration
{
    private const SEEDED = 'Lap time deleted for track limits.';

    private const DESCRIPTION = '2 or more lap times deleted for track limits in one race or sprint.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('achievements')->where('key', 'outside_the_lines')->where('description', self::SEEDED)->update(['description' => self::DESCRIPTION, 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('achievements')->where('key', 'outside_the_lines')->where('description', self::DESCRIPTION)->update(['description' => self::SEEDED, 'updated_at' => now()]);
    }
};
