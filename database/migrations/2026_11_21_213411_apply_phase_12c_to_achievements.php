<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 12c, for rows that already exist (the new glory achievements are
 * created by oof:achievements:sync):
 *
 * - Plot Twist supersedes the new Early Bird (a surprise pole replaces the
 *   pole, as Giant Slayer replaces the win), and Clean Sweep supersedes
 *   Purple Patch (the fastest lap is part of the Grand Chelem).
 * - Did the Thing, Silver Lining and Solid Bronze count Grands Prix only now
 *   (a sprint podium is a Fun Size award); their descriptions say so where
 *   they still equal the seeded text. Their sessions are written by the sync.
 *
 * It refuses to run while the false-start achievement still holds the key
 * early_bird (Phase 12a not applied), so no false-start award can become a
 * pole award.
 */
return new class extends Migration
{
    /**
     * Key => keys added to its supersedes.
     */
    private const SUPERSEDES = [
        'plot_twist' => ['early_bird'],
        'clean_sweep' => ['purple_patch'],
    ];

    /**
     * Key => [seeded description, Grand Prix description].
     */
    private const DESCRIPTIONS = [
        'did_the_thing' => ['Win.', 'Grand Prix win.'],
        'silver_lining' => ['Second place.', 'Second place in a Grand Prix.'],
        'solid_bronze' => ['Third place.', 'Third place in a Grand Prix.'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('achievements')->where('key', 'early_bird')->where('shelf', 'oof')->exists()) {
            throw new RuntimeException('The false-start achievement is still called early_bird: run the Phase 12a rename (rename_early_bird_to_itchy_feet) first.');
        }

        foreach (self::SUPERSEDES as $key => $added) {
            $this->rewriteSupersedes($key, fn (array $keys) => array_values(array_unique([...$keys, ...$added])));
        }

        foreach (self::DESCRIPTIONS as $key => [$seeded, $grandPrix]) {
            DB::table('achievements')->where('key', $key)->where('description', $seeded)->update(['description' => $grandPrix, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::SUPERSEDES as $key => $added) {
            $this->rewriteSupersedes($key, fn (array $keys) => array_values(array_diff($keys, $added)));
        }

        foreach (self::DESCRIPTIONS as $key => [$seeded, $grandPrix]) {
            DB::table('achievements')->where('key', $key)->where('description', $grandPrix)->update(['description' => $seeded, 'updated_at' => now()]);
        }
    }

    /**
     * @param  Closure(list<string>): list<string>  $rewrite
     */
    private function rewriteSupersedes(string $key, Closure $rewrite): void
    {
        $row = DB::table('achievements')->where('key', $key)->first(['id', 'supersedes']);

        if ($row === null) {
            return;
        }

        $keys = json_decode($row->supersedes ?? '[]', true);
        $rewritten = $rewrite($keys);

        if ($rewritten !== $keys) {
            DB::table('achievements')->where('id', $row->id)->update(['supersedes' => json_encode($rewritten), 'updated_at' => now()]);
        }
    }
};
