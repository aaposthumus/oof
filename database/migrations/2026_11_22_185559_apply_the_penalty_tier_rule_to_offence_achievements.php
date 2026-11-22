<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An achievement never supersedes a penalty stamp of a higher tier than its
 * own. The offence achievements (silver and gold) no longer replace the
 * heavier penalty stamps: a silver one keeps Perfect Ten (gold), Drive-Thru
 * and Red Light, Green Light (platinum) beside it, a gold one keeps the two
 * platinum ones. Itchy Feet and Can't Park There were done in
 * limit_what_itchy_feet_and_cant_park_there_supersede. The supersedes lists
 * are editorial, so the rows that exist are rewritten here (the sync writes
 * a new row's list from the catalogue).
 */
return new class extends Migration
{
    /**
     * Offence achievement => the penalty stamps it stops replacing.
     */
    private const SILVER = ['perfect_ten', 'drive_thru', 'red_light_green_light'];

    private const GOLD = ['drive_thru', 'red_light_green_light'];

    /**
     * @var array<string, list<string>>
     */
    private const RELEASED = [
        'bumper_cars' => self::SILVER,
        'smile_for_the_camera' => self::SILVER,
        'sunday_driver' => self::SILVER,
        'double_detention' => self::GOLD,
        'extra_wide' => self::GOLD,
        'failed_the_driving_test' => self::GOLD,
        'queue_jumper' => self::GOLD,
        'released_into_the_wild' => self::GOLD,
        'selective_vision' => self::GOLD,
        'shortcut_tax' => self::GOLD,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::RELEASED as $key => $stamps) {
            $this->rewriteSupersedes($key, fn (array $keys) => array_values(array_diff($keys, $stamps)));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::RELEASED as $key => $stamps) {
            $this->rewriteSupersedes($key, fn (array $keys) => array_values(array_unique([...$keys, ...$stamps])));
        }
    }

    /**
     * @param  Closure(list<string>): list<string>  $rewrite
     */
    private function rewriteSupersedes(string $key, Closure $rewrite): void
    {
        $row = DB::table('achievements')->where('key', $key)->where('shelf', 'oof')->first(['id', 'supersedes']);

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
