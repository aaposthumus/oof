<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fastest Food and Slow Food apply the coverage gate per race (decided by
 * the owner on 22 November 2026): a race with less than 80% of its pit
 * visits timed is left out, and a season needs 90% of its races timed. The
 * descriptions say so where they still equal the seeded text; a CMS edit is
 * kept.
 */
return new class extends Migration
{
    /**
     * Key => [seeded description, new description].
     */
    private const DESCRIPTIONS = [
        'fastest_food' => [
            'Fastest single pit stop of the season, races and sprints, for the team; only in seasons whose races are measured well enough.',
            'Fastest single pit stop of the season, races and sprints, for the team; races with less than 80% of pit visits timed are left out, and a season needs 90% of its races timed.',
        ],
        'slow_food' => [
            'Slowest single pit stop of the season, races and sprints, for the team; stops that served a penalty, under a red flag or ended a race left out; only in seasons whose races are measured well enough.',
            'Slowest single pit stop of the season, races and sprints, for the team; stops that served a penalty, under a red flag or ended a race left out; races with less than 80% of pit visits timed are left out, and a season needs 90% of its races timed.',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::DESCRIPTIONS as $key => [$seeded, $description]) {
            DB::table('achievements')->where('key', $key)->where('description', $seeded)->update(['description' => $description, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::DESCRIPTIONS as $key => [$seeded, $description]) {
            DB::table('achievements')->where('key', $key)->where('description', $description)->update(['description' => $seeded, 'updated_at' => now()]);
        }
    }
};
