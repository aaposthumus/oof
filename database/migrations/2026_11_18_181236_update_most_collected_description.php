<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Most Collected leaves Nothing to See Here out, and says so. A description
 * edited in the CMS no longer equals the seeded one and is kept.
 */
return new class extends Migration
{
    private const SEEDED = 'Most final awards of one OOF achievement in the season (at least 2; a tie is shared).';

    private const NOW = 'Most final awards of one OOF achievement in the season, Nothing to See Here aside (at least 2; a tie is shared).';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('achievements')->where('key', 'most_collected')->where('description', self::SEEDED)->update(['description' => self::NOW, 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('achievements')->where('key', 'most_collected')->where('description', self::NOW)->update(['description' => self::SEEDED, 'updated_at' => now()]);
    }
};
