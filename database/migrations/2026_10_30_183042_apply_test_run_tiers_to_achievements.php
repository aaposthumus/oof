<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Applies the tiers chosen from the 2023–2026 test run to the seeded
 * catalogue, which the seeder never overwrites. Nothing to See Here keeps
 * Bronze but earns no points: it is a slide feature, not an OOF.
 */
return new class extends Migration
{
    /**
     * Key => [old tier, old points, new tier, new points].
     */
    private const CHANGES = [
        'whole_class_detention' => ['gold', 50, 'bronze', 10],
        'recalculating' => ['bronze', 10, 'gold', 50],
        'smile_for_the_camera' => ['bronze', 10, 'silver', 25],
        'high_five' => ['bronze', 10, 'silver', 25],
        'selective_hearing' => ['silver', 25, 'platinum', 100],
        'lane_discipline' => ['silver', 25, 'platinum', 100],
        'drive_thru' => ['gold', 50, 'platinum', 100],
        'road_hog' => ['silver', 25, 'gold', 50],
        'didnt_see_the_sign' => ['silver', 25, 'gold', 50],
        'queue_jumper' => ['silver', 25, 'gold', 50],
        'perfect_ten' => ['silver', 25, 'gold', 50],
        'family_feud' => ['bronze', 10, 'gold', 50],
        'so_close' => ['silver', 25, 'gold', 50],
        'all_dressed_up_nowhere_to_go' => ['silver', 25, 'gold', 50],
        'nothing_to_see_here' => ['bronze', 10, 'bronze', 0],
        'plot_twist' => ['gold', 0, 'platinum', 0],
        'took_the_scenic_route' => ['gold', 0, 'platinum', 0],
        'maths_is_over' => ['gold', 0, 'platinum', 0],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::CHANGES as $key => [, , $tier, $points]) {
            DB::table('achievements')->where('key', $key)->update(['tier' => $tier, 'points' => $points, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::CHANGES as $key => [$tier, $points]) {
            DB::table('achievements')->where('key', $key)->update(['tier' => $tier, 'points' => $points, 'updated_at' => now()]);
        }
    }
};
