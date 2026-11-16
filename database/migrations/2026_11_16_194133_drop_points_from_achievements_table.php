<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OOFscore points are no longer stored per achievement: they follow the
 * tier, from config/achievements.php.
 */
return new class extends Migration
{
    /**
     * The points per tier when the column was dropped.
     */
    private const POINTS = ['bronze' => 10, 'silver' => 25, 'gold' => 50, 'platinum' => 100];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->unsignedSmallInteger('points')->default(0)->after('tier');
        });

        foreach (self::POINTS as $tier => $points) {
            DB::table('achievements')
                ->where('shelf', 'oof')
                ->where('tier', $tier)
                ->where('key', '!=', 'nothing_to_see_here')
                ->update(['points' => $points]);
        }
    }
};
