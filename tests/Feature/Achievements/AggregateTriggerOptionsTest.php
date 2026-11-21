<?php

namespace Tests\Feature\Achievements;

use App\Achievements\AchievementEngine;
use App\Achievements\CatalogueSync;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Facts;
use App\Achievements\OofScoreStandings;
use App\Achievements\Triggers\TriggerFactory;
use App\Enums\RecipientType;
use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\DriverStanding;
use App\Models\MomentSensitivity;
use App\Models\PitLaneVisit;
use App\Models\RaceSession;
use App\Models\Season;
use App\Models\SeasonTeam;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The aggregate trigger's options added in Phase 12b, proven with season
 * awards that exist only in this test: team groups, reduce, tie_break,
 * in_every, skip_sensitive_rounds, coverage, the championship source and
 * the Indianapolis 500 left out of season counts.
 */
class AggregateTriggerOptionsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    /**
     * A season award that is only catalogue data, on the glory side of the Oofies.
     *
     * @param  array<string, mixed>  $trigger
     * @param  list<string>  $sessions
     */
    private function addSeasonAward(string $key, array $trigger, string $recipient = 'driver', array $sessions = ['race']): void
    {
        config(["achievement_catalogue.achievements.{$key}" => [
            'name' => str($key)->headline()->toString(),
            'section' => 'oofies',
            'shelf' => 'oofies',
            'side' => 'glory',
            'tier' => null,
            'score_category' => 'driver',
            'description' => 'Test only.',
            'supersedes' => [],
            'sessions' => $sessions,
            'stacks' => false,
            'recipient' => $recipient,
            'scope' => 'season',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'aggregate', 'source' => 'facts', ...$trigger],
        ]]);

        app(CatalogueSync::class)->apply();
    }

    /**
     * A race of the 2024 season that the engine has run.
     *
     * @param  array<string, mixed>  $round
     */
    private function race(int $roundNumber, array $round = []): RaceSession
    {
        $race = $this->raceSession(SessionType::Race, $this->round(2024, $roundNumber, ['date' => sprintf('2024-03-%02d', $roundNumber * 7), ...$round]));
        $race->update(['achievements_checked_at' => now()]);

        return $race;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finish(RaceSession $race, int $number, Driver $driver, int $position, array $attributes = [], ?SeasonTeam $team = null): DriverEntry
    {
        $entry = $this->car($race, $number, $team, $driver);
        $this->resultFor($entry, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'grid' => $position, 'status' => 'Finished', 'laps_completed' => 50, ...$attributes]);

        return $entry;
    }

    private function stop(RaceSession $race, DriverEntry $entry, ?int $stationary, int $lap = 20): void
    {
        PitLaneVisit::factory()->create(['race_session_id' => $race->id, 'driver_number' => $entry->car_number, 'driver_entry_id' => $entry->id, 'lap_number' => $lap, 'entered_at' => '2024-03-07 20:'.(10 + $lap).':00', 'stop_duration_millis' => $stationary, 'lane_duration_millis' => 22_000]);
    }

    private function runSeason(): void
    {
        $season = Season::firstWhere('year', 2024);
        app(OofScoreStandings::class)->rebuild($season);
        app(AchievementEngine::class)->runSeason($season, now: CarbonImmutable::parse('2024-12-31'));
    }

    public function test_a_team_group_goes_to_the_team_and_reduce_min_takes_its_single_best_value(): void
    {
        $this->addSeasonAward('quickest_stop', ['fact' => 'pit_stop', 'group' => 'team', 'measure' => 'stop_millis', 'reduce' => 'min', 'pick' => 'min', 'detail' => '{value} ms'], 'team');
        $race = $this->race(1);
        $red = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $blue = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $redCar = $this->finish($race, 1, Driver::factory()->create(), 1, team: $red);
        $blueCar = $this->finish($race, 2, Driver::factory()->create(), 2, team: $blue);
        $this->stop($race, $redCar, 2_400, 10);
        $this->stop($race, $redCar, 3_100, 30);
        $this->stop($race, $blueCar, 2_100, 15);

        $this->runSeason();

        $award = $this->awards('quickest_stop')->sole();
        $this->assertSame(RecipientType::Team, $award->recipient_type);
        $this->assertSame($blue->id, $award->season_team_id);
        $this->assertNull($award->driver_id);
        $this->assertSame('2100 ms', $award->detail);
    }

    public function test_reduce_max_takes_the_single_worst_value_not_the_total(): void
    {
        $this->addSeasonAward('slowest_stop', ['fact' => 'pit_stop', 'group' => 'team', 'measure' => 'stop_millis', 'reduce' => 'max', 'pick' => 'max'], 'team');
        $race = $this->race(1);
        $red = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $blue = SeasonTeam::factory()->create(['season_id' => $race->round->season_id]);
        $redCar = $this->finish($race, 1, Driver::factory()->create(), 1, team: $red);
        $blueCar = $this->finish($race, 2, Driver::factory()->create(), 2, team: $blue);

        foreach ([10, 20, 30] as $lap) {
            $this->stop($race, $redCar, 4_000, $lap);
        }

        $this->stop($race, $blueCar, 9_000);

        $this->runSeason();

        $this->assertSame($blue->id, $this->awards('slowest_stop')->sole()->season_team_id);
    }

    public function test_a_tie_on_the_measure_goes_to_the_better_tie_break(): void
    {
        $this->addSeasonAward('most_lapped', ['fact' => 'result', 'where' => [['laps_down', '>=', 1]], 'group' => 'driver', 'measure' => 'count', 'pick' => 'max', 'tie_break' => 'laps_down']);
        $alice = Driver::factory()->create();
        $bob = Driver::factory()->create();
        $carol = Driver::factory()->create();

        foreach ([1, 2] as $roundNumber) {
            $race = $this->race($roundNumber);
            $this->finish($race, 9, Driver::factory()->create(), 1);
            $this->finish($race, 1, $alice, 2, ['status' => '+1 Lap']);
            $this->finish($race, 2, $bob, 3, ['status' => $roundNumber === 1 ? '+2 Laps' : '+1 Lap']);
            $this->finish($race, 3, $carol, 4, ['status' => $roundNumber === 1 ? '+3 Laps' : 'Finished']);
        }

        $this->runSeason();

        // Alice and Bob were lapped in both races; Bob by more laps. Carol's three laps in one race do not count first.
        $this->assertSame([$bob->id], $this->awards('most_lapped')->pluck('driver_id')->all());
    }

    public function test_in_every_needs_an_item_in_every_session_of_that_type(): void
    {
        $this->addSeasonAward('always_scored', ['fact' => 'result', 'where' => [['points', '>', 0.0]], 'group' => 'driver', 'measure' => 'count', 'pick' => 'all', 'in_every' => 'race']);
        $alice = Driver::factory()->create();
        $bob = Driver::factory()->create();
        $first = $this->race(1);
        $this->finish($first, 1, $alice, 1, ['points' => 25]);
        $this->finish($first, 2, $bob, 2, ['points' => 18]);
        $final = $this->race(2);
        $this->finish($final, 1, $alice, 1, ['points' => 25]);
        $this->finish($final, 2, $bob, 11, ['points' => 0]);

        $this->runSeason();

        $this->assertSame([$alice->id], $this->awards('always_scored')->pluck('driver_id')->all());
    }

    public function test_skip_sensitive_rounds_leaves_their_sessions_out_of_the_count(): void
    {
        $this->addSeasonAward('most_retirements', ['fact' => 'result', 'where' => ['retired'], 'group' => 'driver', 'measure' => 'count', 'pick' => 'max', 'skip_sensitive_rounds' => true]);
        $alice = Driver::factory()->create();
        $bob = Driver::factory()->create();
        $first = $this->race(1);
        $this->finish($first, 1, $alice, 1, ['position' => null, 'position_text' => 'R', 'status' => 'Accident']);
        $this->finish($first, 2, $bob, 2);
        $final = $this->race(2);
        $this->finish($final, 1, $alice, 1, ['position' => null, 'position_text' => 'R', 'status' => 'Engine']);
        $this->finish($final, 2, $bob, 2, ['position' => null, 'position_text' => 'R', 'status' => 'Gearbox']);
        MomentSensitivity::factory()->create(['round_id' => $first->round_id]);

        $this->runSeason();

        // Without the sensitive round both retired once, so they share it.
        $this->assertEqualsCanonicalizing([$alice->id, $bob->id], $this->awards('most_retirements')->pluck('driver_id')->all());
    }

    public function test_coverage_gives_nothing_unless_every_race_is_measured_well_enough(): void
    {
        $this->addSeasonAward('quickest_measured_stop', ['fact' => 'pit_stop', 'group' => 'team', 'measure' => 'stop_millis', 'reduce' => 'min', 'pick' => 'min', 'coverage' => ['in' => 'race', 'at_least' => 0.8]], 'team');
        $first = $this->race(1);
        $car = $this->finish($first, 1, Driver::factory()->create(), 1);
        $this->stop($first, $car, 2_300);
        $final = $this->race(2);
        $other = $this->finish($final, 1, Driver::factory()->create(), 1);
        $this->stop($final, $other, 2_500, 10);
        $this->stop($final, $other, null, 30);

        $this->runSeason();
        $this->assertCount(0, $this->awards('quickest_measured_stop'));

        PitLaneVisit::query()->whereNull('stop_duration_millis')->update(['stop_duration_millis' => 2_800]);
        $this->runSeason();

        $this->assertSame($car->season_team_id, $this->awards('quickest_measured_stop')->sole()->season_team_id);
    }

    public function test_a_pit_stop_knows_whether_the_car_rejoined(): void
    {
        $race = $this->race(1);
        $car = $this->finish($race, 1, Driver::factory()->create(), 1, ['position' => null, 'position_text' => 'R', 'status' => 'Retired', 'laps_completed' => 30]);
        $this->stop($race, $car, 2_500, 12);
        $this->stop($race, $car, 40_000, 30);

        $facts = app(Facts::class)->of($this->data($race), 'pit_stop');

        $this->assertSame([true, false], $facts->map(fn (Fact $fact) => $fact->get('rejoined'))->all());
    }

    public function test_the_championship_source_reads_the_final_standings_and_grand_prix_podiums(): void
    {
        config(['achievement_catalogue.achievements.best_without_a_podium' => [
            ...config('achievement_catalogue.achievements.most_collected'),
            'name' => 'Best Without a Podium',
            'side' => 'oof',
            'trigger' => ['type' => 'aggregate', 'source' => 'championship', 'where' => [['podiums', '=', 0], ['points', '>', 0]], 'group' => 'driver', 'measure' => 'position', 'pick' => 'min', 'detail' => 'P{value}'],
        ]]);
        app(CatalogueSync::class)->apply();
        [$alice, $bob, $carol, $dave] = Driver::factory()->count(4)->create()->all();
        $race = $this->race(1);
        $this->finish($race, 1, $alice, 1, ['points' => 25]);
        $this->finish($race, 2, $dave, 2, ['points' => 18]);
        $this->finish($race, 3, $bob, 4, ['points' => 12]);
        $this->finish($race, 4, $carol, 5, ['points' => 10]);

        foreach ([$alice, $dave, $bob, $carol] as $index => $driver) {
            DriverStanding::create(['round_id' => $race->round_id, 'driver_id' => $driver->id, 'position' => $index + 1, 'position_text' => (string) ($index + 1), 'points' => [25, 18, 12, 10][$index], 'wins' => $index === 0 ? 1 : 0]);
        }

        $this->runSeason();

        $award = $this->awards('best_without_a_podium')->sole();
        $this->assertSame([$bob->id, 'P3'], [$award->driver_id, $award->detail]);
    }

    public function test_season_counts_leave_the_indianapolis_500_out(): void
    {
        $this->addSeasonAward('most_wins', ['fact' => 'result', 'where' => [['position', '=', 1]], 'group' => 'driver', 'measure' => 'count', 'pick' => 'max']);
        $alice = Driver::factory()->create();
        $bob = Driver::factory()->create();
        $indy = $this->race(1, ['name' => 'Indianapolis 500']);
        $this->finish($indy, 1, $bob, 1);
        $other = $this->race(2);
        $this->finish($other, 1, $alice, 1);
        $final = $this->race(3);
        $this->finish($final, 1, $bob, 1);

        $this->runSeason();

        // Bob's Indianapolis win does not count, so both have one.
        $this->assertEqualsCanonicalizing([$alice->id, $bob->id], $this->awards('most_wins')->pluck('driver_id')->all());
    }

    public function test_the_trigger_refuses_a_team_award_not_grouped_by_team_and_fact_options_on_other_sources(): void
    {
        $this->addSeasonAward('team_by_season', ['fact' => 'pit_stop', 'group' => 'season', 'measure' => 'count'], 'team');
        $this->addSeasonAward('driver_by_team', ['fact' => 'pit_stop', 'group' => 'team', 'measure' => 'count']);
        config(['achievement_catalogue.achievements.awards_with_reduce' => [
            ...config('achievement_catalogue.achievements.most_collected'),
            'trigger' => [...config('achievement_catalogue.achievements.most_collected.trigger'), 'reduce' => 'min'],
        ]]);

        foreach (['team_by_season', 'driver_by_team', 'awards_with_reduce'] as $key) {
            try {
                app(TriggerFactory::class)->for($key);
                $this->fail("[{$key}] should be refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
