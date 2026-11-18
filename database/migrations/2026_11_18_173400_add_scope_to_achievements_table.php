<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What an achievement is decided over (a session, the weekend or the
 * season) and, for the weekend, after which session. Mechanics, written by
 * oof:achievements:sync; this migration sets them for the achievements
 * that leave the session scope, so the engine is right before the sync runs.
 */
return new class extends Migration
{
    /**
     * Key => [scope, decided after, session types before, session types now].
     */
    private const SCOPES = [
        'overtime' => ['weekend', 'practice', ['practice_1', 'practice_2', 'practice_3'], ['practice_1', 'practice_2', 'practice_3']],
        'practice_makes_imperfect' => ['weekend', 'practice', ['practice_1', 'practice_2', 'practice_3'], ['practice_1', 'practice_2', 'practice_3']],
        'fine_dining' => ['weekend', 'race', ['race'], ['race', 'sprint']],
        'nothing_to_see_here' => ['weekend', 'race', ['race'], ['race']],
        'frequent_flyer' => ['season', null, ['race'], ['race', 'sprint']],
    ];

    /**
     * Key => [seeded description, "whole weekend" description]. A description
     * edited in the CMS no longer equals the seeded one and is kept.
     */
    private const DESCRIPTIONS = [
        'overtime' => ['Most laps driven.', 'Most laps driven in practice over the whole weekend.'],
        'practice_makes_imperfect' => ['Most deleted laps.', 'Most deleted laps in practice over the whole weekend, at least 3.'],
        'fine_dining' => ['Slowest pit stop of the weekend.', 'Slowest pit stop of the whole weekend, sprint included.'],
        'nothing_to_see_here' => ['Unlocked nothing all weekend ("The quiet ones").', 'Unlocked nothing the whole weekend ("The quiet ones").'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->string('scope')->default('session')->after('session_types');
            $table->string('decided_after')->nullable()->after('scope');
        });

        foreach (self::SCOPES as $key => [$scope, $decidedAfter, , $sessions]) {
            DB::table('achievements')->where('key', $key)->update(['scope' => $scope, 'decided_after' => $decidedAfter, 'session_types' => json_encode($sessions), 'updated_at' => now()]);
        }

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

        foreach (self::SCOPES as $key => [, , $sessions]) {
            DB::table('achievements')->where('key', $key)->update(['session_types' => json_encode($sessions), 'updated_at' => now()]);
        }

        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn(['scope', 'decided_after']);
        });
    }
};
