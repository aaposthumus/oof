<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner's Phase 10 decisions of 18 November 2026, for achievement rows
 * that already exist (a new row takes them from the catalogue):
 *
 * - Sibling Rivalry is Silver, by the frequency rule (only while still Gold).
 * - Economy Class leaves out Q1 exits without a lap time (its description,
 *   only while it still equals the seeded text).
 * - One event, one achievement: whatever supersedes It Was Like That When I
 *   Got There (the offences, Sunday Driver and the penalties) also
 *   supersedes Sibling Rivalry, so a teammate penalised for the incident
 *   keeps only the offence.
 *
 * Rebuild the OOFscore of the open seasons afterwards (oof:oofscore), as this
 * bypasses the model's own rebuild.
 */
return new class extends Migration
{
    private const ECONOMY_CLASS_BEFORE = 'A driver from a top-four team on form knocked out in Q1.';

    private const ECONOMY_CLASS_AFTER = 'A driver from a top-four team on form knocked out in Q1 with a lap time.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('achievements')->where('key', 'sibling_rivalry')->where('tier', 'gold')->update(['tier' => 'silver', 'updated_at' => now()]);
        DB::table('achievements')->where('key', 'economy_class')->where('description', self::ECONOMY_CLASS_BEFORE)->update(['description' => self::ECONOMY_CLASS_AFTER, 'updated_at' => now()]);

        $this->eachSuperseder(function (object $row, array $supersedes): ?array {
            return in_array('sibling_rivalry', $supersedes, true) ? null : [...$supersedes, 'sibling_rivalry'];
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('achievements')->where('key', 'sibling_rivalry')->where('tier', 'silver')->update(['tier' => 'gold', 'updated_at' => now()]);
        DB::table('achievements')->where('key', 'economy_class')->where('description', self::ECONOMY_CLASS_AFTER)->update(['description' => self::ECONOMY_CLASS_BEFORE, 'updated_at' => now()]);

        $this->eachSuperseder(function (object $row, array $supersedes): ?array {
            return in_array('sibling_rivalry', $supersedes, true) ? array_values(array_diff($supersedes, ['sibling_rivalry'])) : null;
        });
    }

    /**
     * Rewrite the supersedes list of every achievement but Sibling Rivalry that
     * supersedes It Was Like That When I Got There; null leaves it as it is.
     *
     * @param  Closure(object, list<string>): ?list<string>  $rewrite
     */
    private function eachSuperseder(Closure $rewrite): void
    {
        DB::table('achievements')->where('key', '!=', 'sibling_rivalry')->get(['id', 'supersedes'])->each(function (object $row) use ($rewrite) {
            $supersedes = json_decode($row->supersedes ?? '[]', true) ?? [];

            if (! in_array('it_was_like_that_when_i_got_there', $supersedes, true)) {
                return;
            }

            $new = $rewrite($row, $supersedes);

            if ($new !== null) {
                DB::table('achievements')->where('id', $row->id)->update(['supersedes' => json_encode($new), 'updated_at' => now()]);
            }
        });
    }
};
