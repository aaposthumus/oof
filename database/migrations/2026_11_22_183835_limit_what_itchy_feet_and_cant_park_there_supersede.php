<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Itchy Feet and Can't Park There (gold) replace only the penalty stamps of
 * their own tier or lower (High Five, Perfect Ten). They no longer replace
 * the heavier ones (Drive Thru and Red Light Green Light, platinum): a
 * driver keeps the heavy penalty stamp beside the specific one, so a heavier
 * penalty never earns fewer points while an achievement stays worth the
 * same everywhere in a season. The supersedes lists are editorial, so the
 * rows that exist are rewritten here (the sync writes a new row's list
 * from the catalogue).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const SPECIFIC = ['itchy_feet', 'cant_park_there'];

    /**
     * @var list<string>
     */
    private const HEAVIER = ['drive_thru', 'red_light_green_light'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::SPECIFIC as $key) {
            $this->rewriteSupersedes($key, fn (array $keys) => array_values(array_diff($keys, self::HEAVIER)));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::SPECIFIC as $key) {
            $this->rewriteSupersedes($key, fn (array $keys) => array_values(array_unique([...$keys, ...self::HEAVIER])));
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
