<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings the stored catalogue in line with the OOF! Concept doc: new keys
 * and names (public text uses American spelling), the final tiers, and the
 * podium split into Silver Lining (second) and Solid Bronze (third). The
 * seeder never overwrites the catalogue, so existing rows are changed here.
 * Awards keep their rows, so a person's decisions stay.
 */
return new class extends Migration
{
    /**
     * Old key => [new key, new name].
     */
    private const RENAMES = [
        'mirror_signal_manoeuvre' => ['blue_carpet', 'Blue Carpet'],
        'colouring_outside_the_lines' => ['outside_the_lines', 'Outside the Lines'],
        'note_sent_home_to_parents' => ['teachers_stare', "Teacher's Stare"],
        'recalculating' => ['shortcut_tax', 'Shortcut Tax'],
        'lane_discipline' => ['line_dancing', 'Line Dancing'],
        'road_hog' => ['extra_wide', 'Extra Wide'],
        'didnt_see_the_sign' => ['selective_vision', 'Selective Vision'],
        'family_feud' => ['mirror_match', 'Mirror Match'],
        'so_close' => ['deleted_scene', 'Deleted Scene'],
        'standing_room_only' => ['silver_lining', 'Silver Lining'],
        'lottery_ticket' => ['giant_slayer', 'Giant Slayer'],
        'hogged_the_lot' => ['clean_sweep', 'Clean Sweep'],
        'maths_is_over' => ['class_dismissed', 'Class Dismissed'],
        'spare_tyre' => ['to_the_rescue', 'To the Rescue'],
        'called_in_again' => ['second_helping', 'Second Helping'],
    ];

    /**
     * Old names, to put back on a rollback.
     */
    private const OLD_NAMES = [
        'mirror_signal_manoeuvre' => 'Mirror, Signal, Manoeuvre',
        'colouring_outside_the_lines' => 'Colouring Outside the Lines',
        'note_sent_home_to_parents' => 'Note Sent Home to Parents',
        'recalculating' => 'Recalculating',
        'lane_discipline' => 'Lane Discipline',
        'road_hog' => 'Road Hog',
        'didnt_see_the_sign' => "Didn't See the Sign",
        'family_feud' => 'Family Feud',
        'so_close' => 'So Close',
        'standing_room_only' => 'Standing Room Only',
        'lottery_ticket' => 'Lottery Ticket',
        'hogged_the_lot' => 'Hogged the Lot',
        'maths_is_over' => 'Maths Is Over',
        'spare_tyre' => 'Spare Tyre',
        'called_in_again' => 'Called In Again',
    ];

    /**
     * New key => [old tier, new tier].
     */
    private const TIERS = [
        'meatball_sub' => ['silver', 'platinum'],
        'forgot_to_indicate' => ['silver', 'platinum'],
        'did_the_thing' => ['bronze', 'gold'],
        'silver_lining' => ['bronze', 'silver'],
    ];

    /**
     * New key => [seeded description, description from the concept]. A
     * description edited in the CMS no longer equals the seeded one and is kept.
     */
    private const DESCRIPTIONS = [
        'lapped_dog' => ['Lapped by the leader (finished 1 lap down).', 'Lapped by the leader.'],
        'blue_carpet' => ['Shown blue flags several times in one race.', 'Shown 8 or more blue flags in one race.'],
        'table_for_one' => ['Pit stop longer than 5 s stationary.', 'Pit stop longer than 5 seconds stationary.'],
        'meatball_sub' => ['Black-and-orange flag.', 'Black flag with orange circle ("meatball flag").'],
        'teachers_stare' => ['Black-and-white flag for track limits.', 'Black-and-white warning flag, for any reason (track limits, driving slowly, etc.).'],
        'it_was_like_that_when_i_got_there' => ['Involved in an incident noted by race control.', 'Involved in an incident noted by race control (never implies blame).'],
        'expelled' => ['Black flag during the race.', 'Black flag: thrown out during the race.'],
        'all_dressed_up_nowhere_to_go' => ['Fastest lap but finished outside the points.', 'Fastest lap, but finished outside the points.'],
        'early_bird' => ['False start (offence) with a penalty.', 'False start, with a penalty.'],
        'selective_hearing' => ["Failing to follow race director's instructions, with a penalty.", "Ignoring the race director's instructions, with a penalty."],
        'queue_jumper' => ['Overtaking or going too fast under SC/VSC, with a penalty.', 'Overtaking or going too fast under a safety car or VSC, with a penalty.'],
        'sunday_driver' => ['Maximum delta time offence noted (too slow, e.g. under SC or on a qualifying in-lap).', 'Maximum delta time noted by race control (too slow).'],
        'whole_class_detention' => ['Caused a red flag (practice, qualifying or race).', 'Caused a red flag.'],
        'mirror_match' => ['Knocked out by your own teammate.', 'First car out, and the last car through is your teammate.'],
        'by_a_whisker' => ['Knocked out by less than 0.05 s.', 'Knocked out by less than 0.015 seconds.'],
        'holding_the_door' => ['Slowest in Q1 (unlocks every weekend).', 'Slowest in Q1.'],
        'silver_lining' => ['Podium.', 'Second place.'],
        'clean_sweep' => ['Grand Chelem: pole, win, fastest lap, led every lap.', 'Grand Chelem: pole, win, fastest lap and led every lap.'],
        'took_the_lead' => ['The safety car ran many laps in one race.', 'The safety car ran 8 or more laps in one race.'],
        'second_helping' => ['Several safety car deployments in one race.', '2 or more safety car deployments in one race.'],
        'frequent_flyer' => ['Season count of deployments (for the end-of-season awards).', 'Season count of deployments (end-of-season award).'],
    ];

    /**
     * Keys that superseded the podium before the split. A winner is never
     * second or third, so the split leaves nothing for them to replace.
     */
    private const SUPERSEDED_THE_PODIUM = ['did_the_thing', 'lottery_ticket', 'took_the_scenic_route', 'hogged_the_lot'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::RENAMES as $old => [$key, $name]) {
            DB::table('achievements')->where('key', $old)->update(['key' => $key, 'name' => $name, 'updated_at' => now()]);
        }

        $this->splitThePodium();

        foreach (self::TIERS as $key => [, $tier]) {
            DB::table('achievements')->where('key', $key)->update(['tier' => $tier, 'updated_at' => now()]);
        }

        foreach (self::DESCRIPTIONS as $key => [$seeded, $description]) {
            DB::table('achievements')->where('key', $key)->where('description', $seeded)->update(['description' => $description, 'updated_at' => now()]);
        }

        $newKeys = array_map(fn (array $renamed) => $renamed[0], self::RENAMES);

        $this->rewriteSupersedes(fn (array $keys) => array_values(array_unique(array_diff(
            array_map(fn (string $key) => $newKeys[$key] ?? $key, $keys),
            ['silver_lining'],
        ))));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $silver = DB::table('achievements')->where('key', 'silver_lining')->value('id');
        $bronze = DB::table('achievements')->where('key', 'solid_bronze')->value('id');

        if ($silver !== null) {
            DB::table('achievement_awards')->where('achievement_id', $silver)->whereNull('detail')->update(['detail' => 'P2']);
        }

        if ($silver !== null && $bronze !== null) {
            DB::table('achievement_awards')->where('achievement_id', $bronze)->update(['achievement_id' => $silver, 'detail' => 'P3']);
            DB::table('achievements')->where('id', $bronze)->delete();
        }

        foreach (self::DESCRIPTIONS as $key => [$seeded, $description]) {
            DB::table('achievements')->where('key', $key)->where('description', $description)->update(['description' => $seeded, 'updated_at' => now()]);
        }

        foreach (self::TIERS as $key => [$tier]) {
            DB::table('achievements')->where('key', $key)->update(['tier' => $tier, 'updated_at' => now()]);
        }

        foreach (self::RENAMES as $old => [$key]) {
            DB::table('achievements')->where('key', $key)->update(['key' => $old, 'name' => self::OLD_NAMES[$old], 'updated_at' => now()]);
        }

        $oldKeys = array_flip(array_map(fn (array $renamed) => $renamed[0], self::RENAMES));

        $this->rewriteSupersedes(fn (array $keys, string $own) => array_values(array_unique([
            ...array_map(fn (string $key) => $oldKeys[$key] ?? $key, $keys),
            ...(in_array($own, self::SUPERSEDED_THE_PODIUM, true) ? ['standing_room_only'] : []),
        ])));
    }

    /**
     * Standing Room Only has become Silver Lining; its third places move to
     * a new Solid Bronze. The name says the place now, so the detail goes.
     */
    private function splitThePodium(): void
    {
        $silver = DB::table('achievements')->where('key', 'silver_lining')->first();

        if ($silver === null || DB::table('achievements')->where('key', 'solid_bronze')->exists()) {
            return;
        }

        $bronze = DB::table('achievements')->insertGetId([
            'key' => 'solid_bronze',
            'name' => 'Solid Bronze',
            'section' => $silver->section,
            'shelf' => $silver->shelf,
            'tier' => 'bronze',
            'session_types' => $silver->session_types,
            'stacks' => $silver->stacks,
            'supersedes' => json_encode([]),
            'description' => 'Third place.',
            'is_enabled' => $silver->is_enabled,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('achievement_awards')->where('achievement_id', $silver->id)->where('detail', 'P3')->update(['achievement_id' => $bronze, 'detail' => null]);
        DB::table('achievement_awards')->where('achievement_id', $silver->id)->where('detail', 'P2')->update(['detail' => null]);
    }

    /**
     * @param  Closure(list<string>, string): list<string>  $rewrite  Given the superseded keys and the achievement's own key
     */
    private function rewriteSupersedes(Closure $rewrite): void
    {
        foreach (DB::table('achievements')->get(['id', 'key', 'supersedes']) as $achievement) {
            $keys = json_decode($achievement->supersedes, true);
            $rewritten = $rewrite($keys, $achievement->key);

            if ($rewritten !== $keys) {
                DB::table('achievements')->where('id', $achievement->id)->update(['supersedes' => json_encode($rewritten), 'updated_at' => now()]);
            }
        }
    }
};
