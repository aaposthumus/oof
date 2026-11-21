<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The false-start achievement was called Early Bird; it is Itchy Feet now
 * (decided by the owner on 21 November 2026), so the name Early Bird is free
 * for the pole-position achievement on the glory shelf. Awards point to the
 * achievement row, so renaming the row's key moves every stored award, with
 * every person's decision on it. The name changes only where it is still the
 * seeded one; a name edited in the CMS is kept.
 *
 * Run this before oof:achievements:sync. Should a sync have run first, it
 * created an empty Itchy Feet row beside the old one: that row is removed
 * when it holds no awards, and otherwise the migration stops.
 */
return new class extends Migration
{
    private const OLD_KEY = 'early_bird';

    private const NEW_KEY = 'itchy_feet';

    private const OLD_NAME = 'Early Bird';

    private const NEW_NAME = 'Itchy Feet';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $falseStart = DB::table('achievements')->where('key', self::OLD_KEY)->where('shelf', 'oof')->first();

        if ($falseStart === null) {
            return;
        }

        $this->removeEmptyRow(self::NEW_KEY);

        DB::table('achievements')->where('id', $falseStart->id)->update(['key' => self::NEW_KEY, 'updated_at' => now()]);
        DB::table('achievements')->where('id', $falseStart->id)->where('name', self::OLD_NAME)->update(['name' => self::NEW_NAME]);

        $this->rewriteSupersedes(self::OLD_KEY, self::NEW_KEY);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $falseStart = DB::table('achievements')->where('key', self::NEW_KEY)->first();

        if ($falseStart === null) {
            return;
        }

        $this->removeEmptyRow(self::OLD_KEY);

        DB::table('achievements')->where('id', $falseStart->id)->update(['key' => self::OLD_KEY, 'updated_at' => now()]);
        DB::table('achievements')->where('id', $falseStart->id)->where('name', self::NEW_NAME)->update(['name' => self::OLD_NAME]);

        $this->rewriteSupersedes(self::NEW_KEY, self::OLD_KEY);
    }

    /**
     * Removes a row under the target key that a sync created ahead of this
     * migration, as long as nothing was awarded under it.
     */
    private function removeEmptyRow(string $key): void
    {
        $existing = DB::table('achievements')->where('key', $key)->first();

        if ($existing === null) {
            return;
        }

        if (DB::table('achievement_awards')->where('achievement_id', $existing->id)->exists()) {
            throw new RuntimeException("Cannot rename: [{$key}] already exists and holds awards. Merge it by hand first.");
        }

        DB::table('achievement_variants')->where('achievement_id', $existing->id)->delete();
        DB::table('achievements')->where('id', $existing->id)->delete();
    }

    private function rewriteSupersedes(string $from, string $to): void
    {
        foreach (DB::table('achievements')->get(['id', 'supersedes']) as $achievement) {
            $keys = json_decode($achievement->supersedes ?? '[]', true);

            if (in_array($from, $keys, true)) {
                $rewritten = array_values(array_unique(array_map(fn (string $key) => $key === $from ? $to : $key, $keys)));
                DB::table('achievements')->where('id', $achievement->id)->update(['supersedes' => json_encode($rewritten), 'updated_at' => now()]);
            }
        }
    }
};
