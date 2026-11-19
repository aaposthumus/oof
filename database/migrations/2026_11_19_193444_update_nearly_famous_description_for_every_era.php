<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nearly Famous is the first place outside the points of each era (Phase
 * 11b, D11), not always P11. The description changes only where it still
 * equals the seeded text, so a CMS edit is kept.
 */
return new class extends Migration
{
    private const BEFORE = 'Finished P11, just outside the points.';

    private const AFTER = 'Finished just outside the points: P11 since 2010, the first place without points in earlier eras.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('achievements')->where('key', 'nearly_famous')->where('description', self::BEFORE)->update(['description' => self::AFTER, 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('achievements')->where('key', 'nearly_famous')->where('description', self::AFTER)->update(['description' => self::BEFORE, 'updated_at' => now()]);
    }
};
