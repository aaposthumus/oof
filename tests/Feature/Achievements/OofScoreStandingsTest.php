<?php

namespace Tests\Feature\Achievements;

use App\Achievements\OofScoreStandings;
use App\Achievements\SeasonNotClosable;
use App\Enums\AchievementTier;
use App\Enums\AwardStatus;
use App\Enums\ScoreCategory;
use App\Enums\SessionType;
use App\Events\OofScoreStandingsUpdated;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\OofscoreStanding;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\SeasonTeam;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class OofScoreStandingsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    private function race(int $roundNumber, int $year = 2024): RaceSession
    {
        $race = $this->raceSession(SessionType::Race, $this->round($year, $roundNumber, ['date' => "{$year}-0".min(9, $roundNumber).'-01']));
        $race->update(['achievements_checked_at' => now()]);
        $this->resultFor($this->car($race, 99), ['position' => 1, 'position_text' => '1']);

        return $race;
    }

    private function award(RaceSession $race, Driver $driver, string $key, AwardStatus $status = AwardStatus::Final): AchievementAward
    {
        return AchievementAward::factory()->create([
            'race_session_id' => $race->id,
            'achievement_id' => Achievement::firstWhere('key', $key)->id,
            'driver_id' => $driver->id,
            'status' => $status,
        ]);
    }

    /**
     * @return array<int, array{float, int, int, int|null}> Driver ID => [points, awards, position, change]
     */
    private function after(RaceSession $race): array
    {
        return OofscoreStanding::query()->where('round_id', $race->round_id)->get()
            ->mapWithKeys(fn (OofscoreStanding $row) => [$row->driver_id => [$row->points, $row->awards_count, $row->position, $row->position_change]])
            ->sortKeys()
            ->all();
    }

    public function test_sums_final_oof_awards_round_by_round_and_leaves_out_the_rest(): void
    {
        [$alice, $bob, $carol] = Driver::factory()->count(3)->create();
        $first = $this->race(1);
        $second = $this->race(2);
        $this->award($first, $alice, 'lapped_dog');               // bronze, 10
        $this->award($first, $bob, 'good_boy');                   // silver, 25
        $this->award($first, $carol, 'lapped_dog', AwardStatus::Pending);
        $this->award($first, $carol, 'did_the_thing');            // glory: no points
        $this->award($second, $alice, 'forgot_the_keys');         // gold, 50
        $this->award($second, $bob, 'outside_the_lines', AwardStatus::Suppressed);
        $this->award($second, $carol, 'nothing_to_see_here');     // 0 points, but an award

        app(OofScoreStandings::class)->rebuild($first->round->season);

        $this->assertSame([$alice->id => [10.0, 1, 2, null], $bob->id => [25.0, 1, 1, null]], $this->after($first));
        $this->assertSame([$alice->id => [60.0, 2, 1, 1], $bob->id => [25.0, 1, 2, -1], $carol->id => [0.0, 1, 3, null]], $this->after($second));
        $this->assertSame([$alice->id, $bob->id, $carol->id], array_keys($this->after($second)), 'Drivers in ID order, for the comparison.');
    }

    public function test_equal_points_are_split_like_a_medal_table_and_only_full_equals_share_a_place(): void
    {
        [$platinum, $gold, $silvers, $bronzes, $twin] = Driver::factory()->count(5)->create();
        $race = $this->race(1);
        $this->award($race, $platinum, 'selective_hearing');   // one platinum: 100
        $this->award($race, $gold, 'forgot_the_keys');        // two golds: 100
        $this->award($race, $gold, 'forgot_the_keys');
        $this->award($race, $silvers, 'good_boy');            // four silvers: 100
        $this->award($race, $silvers, 'good_boy');
        $this->award($race, $silvers, 'good_boy');
        $this->award($race, $silvers, 'good_boy');
        foreach ([$bronzes, $twin] as $driver) {              // ten bronzes each: 100
            foreach (range(1, 10) as $ignored) {
                $this->award($race, $driver, 'lapped_dog');
            }
        }

        app(OofScoreStandings::class)->rebuild($race->round->season);

        $places = array_map(fn (array $row) => $row[2], $this->after($race));
        $this->assertSame([$platinum->id => 1, $gold->id => 2, $silvers->id => 3, $bronzes->id => 4, $twin->id => 4], $places);
    }

    public function test_nothing_to_see_here_never_helps_in_a_tie(): void
    {
        [$quiet, $other] = Driver::factory()->count(2)->create();
        $race = $this->race(1);
        $this->award($race, $quiet, 'lapped_dog');
        $this->award($race, $quiet, 'nothing_to_see_here');
        $this->award($race, $other, 'lapped_dog');

        app(OofScoreStandings::class)->rebuild($race->round->season);

        $after = $this->after($race);
        $this->assertSame([10.0, 2, 1, null], $after[$quiet->id], 'It still shows as an award.');
        $this->assertSame([10.0, 1, 1, null], $after[$other->id], 'But both share the place.');
    }

    /**
     * Eight lasting teams with a race in round 1 that ranks them 1 to 8 on form, and
     * a car of the teams ranked 1, 6 and 8 in round 2.
     *
     * @return array{RaceSession, array<int, DriverEntry>} The round 2 race and its cars by form rank
     */
    private function formField(): array
    {
        $first = $this->race(1);
        $second = $this->race(2);
        $cars = [];

        foreach (range(1, 8) as $rank) {
            $team = SeasonTeam::factory()->create(['season_id' => $first->round->season_id]);
            $this->resultFor($this->car($first, 10 + $rank, $team), ['points' => 30 - $rank]);

            if (in_array($rank, [1, 6, 8], true)) {
                $cars[$rank] = $this->car($second, 10 + $rank, $team);
            }
        }

        return [$second, $cars];
    }

    private function awardFor(RaceSession $race, DriverEntry $car, string $key): AchievementAward
    {
        return AchievementAward::factory()->create([
            'race_session_id' => $race->id,
            'achievement_id' => Achievement::firstWhere('key', $key)->id,
            'driver_entry_id' => $car->id,
            'driver_id' => $car->driver_id,
        ]);
    }

    public function test_pace_and_reliability_awards_are_weighed_by_team_form_and_driver_awards_are_not(): void
    {
        [$race, $cars] = $this->formField();
        $awards = [];

        foreach ($cars as $rank => $car) {
            foreach (['lapped_dog', 'forgot_the_keys', 'outside_the_lines'] as $key) {
                $awards[$rank][$key] = $this->awardFor($race, $car, $key);
            }
        }

        app(OofScoreStandings::class)->rebuild($race->round->season);

        // Pace: 10 × 2 / 1 / 0.5. Reliability: 50 × 1 / 1 / 0.5. Driver: 10 × 1.
        $this->assertSame(80.0, $this->after($race)[$cars[1]->driver_id][0]);
        $this->assertSame(70.0, $this->after($race)[$cars[6]->driver_id][0]);
        $this->assertSame(40.0, $this->after($race)[$cars[8]->driver_id][0]);

        $lapped = $awards[1]['lapped_dog']->fresh();
        $this->assertSame([10, AchievementTier::Bronze, 1, 2.0], [$lapped->points, $lapped->tier, $lapped->team_form_rank, $lapped->score_multiplier]);
        $this->assertSame([8, 0.5], [$awards[8]['forgot_the_keys']->fresh()->team_form_rank, $awards[8]['forgot_the_keys']->fresh()->score_multiplier]);
        $this->assertSame(1.0, $awards[1]['outside_the_lines']->fresh()->score_multiplier);
    }

    public function test_changing_an_achievements_score_category_reaches_the_open_seasons(): void
    {
        [$race, $cars] = $this->formField();
        $award = $this->awardFor($race, $cars[1], 'outside_the_lines');
        app(OofScoreStandings::class)->rebuild($race->round->season);
        $this->assertSame(10.0, $this->after($race)[$cars[1]->driver_id][0]);

        $award->achievement->update(['score_category' => ScoreCategory::Pace]);

        $this->assertSame(2.0, $award->fresh()->score_multiplier);
        $this->assertSame(20.0, $this->after($race)[$cars[1]->driver_id][0]);
    }

    public function test_a_closed_season_keeps_its_stored_multipliers(): void
    {
        [$race, $cars] = $this->formField();
        $award = $this->awardFor($race, $cars[1], 'lapped_dog');
        $standings = app(OofScoreStandings::class);
        $standings->close($race->round->season, CarbonImmutable::parse('2024-12-31'));

        $award->achievement->update(['score_category' => ScoreCategory::Driver]);
        config(['achievements.points.multipliers.pace.top' => 3.0]);
        $standings->rebuild($race->round->season->fresh());

        $this->assertSame(2.0, $award->fresh()->score_multiplier);
        $this->assertSame(20.0, $this->after($race)[$cars[1]->driver_id][0]);
    }

    public function test_the_medal_tie_break_counts_tiers_not_multiplied_points(): void
    {
        [$race, $cars] = $this->formField();
        $this->awardFor($race, $cars[1], 'lapped_dog');              // one bronze: 10 × 2 = 20

        foreach (['lapped_dog', 'blue_carpet', 'holding_the_door', 'turned_off_the_lights'] as $key) {
            $this->awardFor($race, $cars[8], $key);                  // four bronzes: 4 × 10 × 0.5 = 20
        }

        app(OofScoreStandings::class)->rebuild($race->round->season);

        $after = $this->after($race);
        $this->assertSame([20.0, 20.0], [$after[$cars[1]->driver_id][0], $after[$cars[8]->driver_id][0]]);
        $this->assertSame([2, 1], [$after[$cars[1]->driver_id][2], $after[$cars[8]->driver_id][2]]);
    }

    public function test_a_rebuild_from_scratch_gives_the_same_standings_and_changes_nothing(): void
    {
        [$alice, $bob] = Driver::factory()->count(2)->create();
        $race = $this->race(1);
        $this->award($race, $alice, 'good_boy');
        $this->award($race, $bob, 'lapped_dog');
        $standings = app(OofScoreStandings::class);

        $standings->rebuild($race->round->season);
        $before = $this->after($race);
        OofscoreStanding::query()->delete();
        $standings->rebuild($race->round->season);

        $this->assertSame($before, $this->after($race));
        $this->assertSame(0, $standings->rebuild($race->round->season)['changed_rounds']);
    }

    public function test_a_round_without_awards_adds_no_row(): void
    {
        $alice = Driver::factory()->create();
        $first = $this->race(1);
        $empty = $this->race(2);
        $this->award($first, $alice, 'lapped_dog');

        app(OofScoreStandings::class)->rebuild($first->round->season);

        $this->assertSame([], $this->after($empty));
    }

    public function test_closing_a_season_freezes_its_standings_and_points(): void
    {
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $award = $this->award($race, $alice, 'lapped_dog');
        $standings = app(OofScoreStandings::class);

        $standings->close($race->round->season, CarbonImmutable::parse('2024-12-31'));

        $this->assertTrue($race->round->season->fresh()->isClosed());
        $this->assertSame([$alice->id => [10.0, 1, 1, null]], $this->after($race));

        $this->award($race, $alice, 'good_boy');
        $award->achievement->update(['tier' => AchievementTier::Platinum]);

        $this->assertNull($standings->rebuild($race->round->season->fresh()));
        $this->assertSame([$alice->id => [10.0, 1, 1, null]], $this->after($race));
        $this->assertSame(10, $award->fresh()->points);
    }

    public function test_reopening_a_season_brings_its_points_back_to_the_catalogue_and_rebuilds_it(): void
    {
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $award = $this->award($race, $alice, 'lapped_dog');
        $standings = app(OofScoreStandings::class);
        $standings->close($race->round->season, CarbonImmutable::parse('2024-12-31'));
        $award->achievement->update(['tier' => AchievementTier::Silver]);
        $this->award($race, $alice, 'good_boy');

        $standings->reopen($race->round->season->fresh());

        $this->assertFalse($race->round->season->fresh()->isClosed());
        $this->assertSame(25, $award->fresh()->points);
        $this->assertSame([$alice->id => [50.0, 2, 1, null]], $this->after($race));

        $this->expectException(SeasonNotClosable::class);
        $standings->reopen($race->round->season->fresh());
    }

    public function test_the_reopen_command_asks_for_the_year(): void
    {
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $this->award($race, $alice, 'lapped_dog');
        app(OofScoreStandings::class)->close($race->round->season, CarbonImmutable::parse('2024-12-31'));

        $this->artisan('oof:oofscore:reopen', ['season' => 2024])
            ->expectsQuestion("Reopening 2024 gives its awards the catalogue's current points and rebuilds its OOFscore. Type the year to go on", '2023')
            ->expectsOutputToContain('Not reopened.')
            ->assertFailed();
        $this->assertTrue($race->round->season->fresh()->isClosed());

        $this->artisan('oof:oofscore:reopen', ['season' => 2024])
            ->expectsQuestion("Reopening 2024 gives its awards the catalogue's current points and rebuilds its OOFscore. Type the year to go on", '2024')
            ->expectsOutputToContain('2024 is open again.')
            ->assertSuccessful();
        $this->assertFalse($race->round->season->fresh()->isClosed());

        $this->artisan('oof:oofscore:reopen', ['season' => 2024, '--force' => true])
            ->expectsOutputToContain('it is not closed')
            ->assertFailed();
    }

    public function test_a_season_cannot_be_closed_before_its_last_race_is_final_or_while_awards_wait(): void
    {
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $pending = $this->award($race, $alice, 'lapped_dog', AwardStatus::Pending);
        $standings = app(OofScoreStandings::class);

        $this->assertSame(['its last race is not final yet', '1 awards still wait for review'], $standings->reasonsNotToClose($race->round->season, CarbonImmutable::parse('2024-01-02')));

        $pending->update(['status' => AwardStatus::Final]);
        $race->update(['achievements_checked_at' => null]);
        $this->assertSame(['its last race has not been run by the achievements engine'], $standings->reasonsNotToClose($race->round->season, CarbonImmutable::parse('2024-12-31')));

        $race->update(['achievements_checked_at' => now()]);
        $standings->close($race->round->season, CarbonImmutable::parse('2024-12-31'));

        $this->expectException(SeasonNotClosable::class);
        $standings->close($race->round->season->fresh(), CarbonImmutable::parse('2024-12-31'));
    }

    public function test_the_standings_event_is_dispatched_only_for_live_seasons_and_only_on_a_change(): void
    {
        Event::fake([OofScoreStandingsUpdated::class]);
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $this->award($race, $alice, 'lapped_dog');
        $standings = app(OofScoreStandings::class);

        config(['achievements.events_from' => 2025]);
        $standings->rebuild($race->round->season);
        Event::assertNotDispatched(OofScoreStandingsUpdated::class);

        config(['achievements.events_from' => 2024]);
        OofscoreStanding::query()->delete();
        $standings->rebuild($race->round->season);
        Event::assertDispatched(OofScoreStandingsUpdated::class, fn (OofScoreStandingsUpdated $event) => $event->seasonId === $race->round->season_id && $event->changedRoundIds === [$race->round_id]);

        $standings->rebuild($race->round->season);
        Event::assertDispatchedTimes(OofScoreStandingsUpdated::class, 1);
    }

    public function test_the_engine_run_rebuilds_the_seasons_it_touched(): void
    {
        config(['achievements.first_season' => 2024]);
        $race = $this->raceSession();
        $car = $this->car($race, 44);
        $this->resultFor($car, ['position' => 7, 'position_text' => '7', 'grid' => 7]);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE');
        $this->travelTo(CarbonImmutable::parse('2024-06-01'));

        $this->artisan('oof:achievements', ['--season' => [2024]])->assertSuccessful();

        $counted = AchievementAward::query()->where('driver_id', $car->driver_id)->where('status', AwardStatus::Final)->where('shelf', 'oof')->get();
        $this->assertContains('smile_for_the_camera', $counted->map(fn (AchievementAward $award) => $award->achievement->key));
        $this->assertSame([$car->driver_id => [(float) $counted->sum(fn (AchievementAward $award) => $award->points * $award->score_multiplier), $counted->count(), 1, null]], $this->after($race));
    }

    public function test_the_commands_rebuild_and_close(): void
    {
        config(['achievements.first_season' => 2024]);
        $alice = Driver::factory()->create();
        $race = $this->race(1);
        $this->award($race, $alice, 'lapped_dog');
        $this->travelTo(CarbonImmutable::parse('2024-12-31'));

        $this->artisan('oof:oofscore', ['--season' => [2024]])->expectsOutputToContain('rebuilt')->assertSuccessful();
        $this->artisan('oof:oofscore:close', ['season' => 2024, '--force' => true])->expectsOutputToContain('2024 is closed.')->assertSuccessful();
        $this->artisan('oof:oofscore', ['--season' => [2024]])->expectsOutputToContain('closed, frozen')->assertSuccessful();
        $this->artisan('oof:oofscore:close', ['season' => 2024, '--force' => true])->expectsOutputToContain('it is closed already')->assertFailed();
        $this->assertInstanceOf(Round::class, $race->round);
    }
}
