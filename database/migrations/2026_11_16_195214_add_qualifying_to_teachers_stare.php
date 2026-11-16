<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Teacher's Stare also counts in qualifying and sprint qualifying, where
 * most black-and-white flags for driving slowly are shown; not in practice.
 * The seeder never overwrites the catalogue, so the existing row is changed
 * here, unless its sessions or description were edited in the CMS.
 */
return new class extends Migration
{
    private const RACES = ['race', 'sprint'];

    private const COMPETITIVE = ['race', 'sprint', 'qualifying', 'sprint_qualifying'];

    private const RACE_DESCRIPTION = 'Black-and-white warning flag, for any reason (track limits, driving slowly, etc.).';

    private const COMPETITIVE_DESCRIPTION = 'Black-and-white warning flag in a race or qualifying, for any reason (track limits, driving slowly, etc.).';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->change(self::RACES, self::COMPETITIVE, self::RACE_DESCRIPTION, self::COMPETITIVE_DESCRIPTION);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->change(self::COMPETITIVE, self::RACES, self::COMPETITIVE_DESCRIPTION, self::RACE_DESCRIPTION);
    }

    /**
     * @param  list<string>  $fromSessions
     * @param  list<string>  $toSessions
     */
    private function change(array $fromSessions, array $toSessions, string $fromDescription, string $toDescription): void
    {
        $stare = DB::table('achievements')->where('key', 'teachers_stare')->first(['id', 'session_types']);

        if ($stare !== null && json_decode($stare->session_types, true) === $fromSessions) {
            DB::table('achievements')->where('id', $stare->id)->update(['session_types' => json_encode($toSessions), 'updated_at' => now()]);
        }

        DB::table('achievements')->where('key', 'teachers_stare')->where('description', $fromDescription)->update(['description' => $toDescription, 'updated_at' => now()]);
    }
};
