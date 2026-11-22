<?php

namespace Tests\Feature\Achievements;

use App\Achievements\AchievementEngine;
use App\Achievements\OofScoreStandings;
use App\Enums\AchievementShelf;
use App\Enums\RecipientType;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\DriverStanding;
use App\Models\LapTime;
use App\Models\MomentSensitivity;
use App\Models\PitLaneVisit;
use App\Models\RaceSession;
use App\Models\Season;
use App\Models\SeasonTeam;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The fourteen season Oofies of Phase 12e, each on a tiny season: ties
 * shared, minimums, the every-Grand-Prix rules with a missed round, the
 * coverage gate of the pit stop awards, Always the Bridesmaid by the
 * standings, the OOF side absent before 2023 and the glory side present in
 * 1990.
 */
class SeasonOofiesTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    private function race(int $year, int $roundNumber): RaceSession
    {
        $race = $this->raceSession(SessionType::Race, $this->round($year, $roundNumber, ['date' => sprintf('%d-0%d-01', $year, $roundNumber)]));
        $race->update(['achievements_checked_at' => now()]);

        return $race;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function finish(RaceSession $race, int $number, Driver $driver, int $position, array $result = [], ?SeasonTeam $team = null): DriverEntry
    {
        $entry = $this->car($race, $number, $team, $driver);
        $this->resultFor($entry, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'grid' => $position, 'status' => 'Finished', 'laps_completed' => 50, 'points' => max(0, 11 - $position), ...$result]);

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function retirement(string $status = 'Engine'): array
    {
        return ['position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => $status, 'laps_completed' => 20, 'points' => 0];
    }

    private function runSeason(int $year): void
    {
        $season = Season::firstWhere('year', $year);
        app(OofScoreStandings::class)->rebuild($season);
        app(AchievementEngine::class)->runSeason($season, now: CarbonImmutable::parse("{$year}-12-31"));
    }

    /**
     * @return list<int>
     */
    private function winners(string $key): array
    {
        return $this->awards($key)->pluck('driver_id')->sort()->values()->all();
    }

    /**
     * @return array{0: Driver, 1: Driver, 2: Driver}
     */
    private function drivers(): array
    {
        return Driver::factory()->count(3)->create()->sortBy('id')->values()->all();
    }

    public function test_creature_of_habit_is_most_wins_and_a_tie_is_shared(): void
    {
        [$alice, $bob, $carol] = $this->drivers();
        $this->finish($this->race(2024, 1), 1, $alice, 1);
        $this->finish($this->race(2024, 2), 2, $bob, 1);
        $third = $this->race(2024, 3);
        $this->finish($third, 1, $alice, 1);
        $this->finish($third, 3, $carol, 2);

        $this->runSeason(2024);
        $this->assertSame([$alice->id], $this->winners('creature_of_habit'));
        $this->assertSame('2 wins', $this->awards('creature_of_habit')->sole()->detail);
        $this->assertSame([AchievementShelf::Oofies, 0], [$this->awards('creature_of_habit')->sole()->shelf, $this->awards('creature_of_habit')->sole()->points]);

        $this->finish($this->race(2024, 4), 2, $bob, 1);
        $this->runSeason(2024);
        $this->assertSame([$alice->id, $bob->id], $this->winners('creature_of_habit'));
    }

    public function test_morning_person_counts_qualifying_poles_and_the_glory_side_reaches_back_to_1990(): void
    {
        [$alice, $bob] = $this->drivers();

        foreach ([1, 2] as $roundNumber) {
            $race = $this->race(2024, $roundNumber);
            $qualifying = $this->raceSession(SessionType::Qualifying, $race->round);
            $this->qualifying($this->car($qualifying, 1, null, $alice), 1, 80_000);
            $this->qualifying($this->car($qualifying, 2, null, $bob), 2, 80_100);
            $this->finish($race, 1, $alice, 1);
        }

        $this->runSeason(2024);
        $this->assertSame([$alice->id], $this->winners('morning_person'));
        $this->assertSame('2 poles', $this->awards('morning_person')->sole()->detail);

        // 1990: no qualifying data, so grid 1 is the pole.
        $old = $this->race(1990, 1);
        $this->finish($old, 2, $bob, 2, ['grid' => 1]);
        $this->finish($old, 1, $alice, 1, ['grid' => 2]);
        $this->runSeason(1990);
        $this->assertSame([$bob->id], AchievementAward::query()->whereRelation('achievement', 'key', 'morning_person')->whereRelation('season', 'year', 1990)->pluck('driver_id')->all());
    }

    public function test_room_with_a_view_is_most_podiums_and_a_sprint_does_not_count(): void
    {
        [$alice, $bob] = $this->drivers();
        $first = $this->race(2024, 1);
        $this->finish($first, 1, $alice, 3);
        $this->finish($first, 2, $bob, 4);
        $sprint = $this->raceSession(SessionType::Sprint, $first->round);
        $this->finish($sprint, 2, $bob, 1);
        $final = $this->race(2024, 2);
        $this->finish($final, 2, $bob, 5);
        $sprintTwo = $this->raceSession(SessionType::Sprint, $final->round);
        $this->finish($sprintTwo, 2, $bob, 2);

        $this->runSeason(2024);

        $this->assertSame([$alice->id], $this->winners('room_with_a_view'));
    }

    public function test_follow_the_leader_is_most_laps_led_from_1996(): void
    {
        [$alice, $bob] = $this->drivers();
        $race = $this->race(2024, 1);
        $leader = $this->finish($race, 1, $alice, 2, ['laps_completed' => 3]);
        $winner = $this->finish($race, 2, $bob, 1, ['laps_completed' => 3]);

        foreach ([1 => [1, 2], 2 => [1, 2], 3 => [2, 1]] as $lap => [$leaderPosition, $winnerPosition]) {
            LapTime::create(['driver_entry_id' => $leader->id, 'lap' => $lap, 'position' => $leaderPosition, 'time_millis' => 90_000]);
            LapTime::create(['driver_entry_id' => $winner->id, 'lap' => $lap, 'position' => $winnerPosition, 'time_millis' => 90_000]);
        }

        $this->runSeason(2024);
        $this->assertSame([$alice->id], $this->winners('follow_the_leader'));
        $this->assertSame('2 laps led', $this->awards('follow_the_leader')->sole()->detail);

        $this->finish($this->race(1990, 1), 1, $alice, 1);
        $this->runSeason(1990);
        $this->assertFalse(AchievementAward::query()->whereRelation('achievement', 'key', 'follow_the_leader')->whereRelation('season', 'year', 1990)->exists());
    }

    public function test_stampede_and_backpedal_add_up_gains_and_losses_of_classified_finishers_only(): void
    {
        [$alice, $bob, $carol] = $this->drivers();
        $first = $this->race(2024, 1);
        $this->finish($first, 1, $alice, 2, ['grid' => 12]);       // +10
        $this->finish($first, 2, $bob, 5, ['grid' => 1]);          // -4
        $this->finish($first, 3, $carol, 0, ['grid' => 2, ...$this->retirement()]);
        $final = $this->race(2024, 2);
        $this->finish($final, 1, $alice, 8, ['grid' => 3]);        // -5: a loss does not take away a gain
        $this->finish($final, 2, $bob, 3, ['grid' => 6]);          // +3
        $this->finish($final, 3, $carol, 20, ['grid' => 1]);       // -19

        $this->runSeason(2024);

        $this->assertSame([$alice->id], $this->winners('stampede'));
        $this->assertSame('10 places gained', $this->awards('stampede')->sole()->detail);
        $this->assertSame([$carol->id], $this->winners('backpedal'));
        $this->assertSame('19 places lost', $this->awards('backpedal')->sole()->detail);
    }

    /**
     * Two races of 2025, a red and a blue car with measured stops.
     *
     * @return array{0: SeasonTeam, 1: SeasonTeam, 2: list<DriverEntry>}
     */
    private function pitStopSeason(): array
    {
        $races = [$this->race(2025, 1), $this->race(2025, 2)];
        $red = SeasonTeam::factory()->create(['season_id' => $races[0]->round->season_id]);
        $blue = SeasonTeam::factory()->create(['season_id' => $races[0]->round->season_id]);
        $cars = [];

        foreach ($races as $index => $race) {
            $cars[] = $this->finish($race, 1, Driver::factory()->create(), 1, team: $red);
            $cars[] = $this->finish($race, 2, Driver::factory()->create(), 2, team: $blue);
        }

        return [$red, $blue, $cars];
    }

    private function stop(DriverEntry $car, ?int $stationary, int $lap = 20): void
    {
        PitLaneVisit::factory()->create(['race_session_id' => $car->race_session_id, 'driver_number' => $car->car_number, 'driver_entry_id' => $car->id, 'lap_number' => $lap, 'entered_at' => '2025-01-01 20:'.(10 + $lap).':00', 'stop_duration_millis' => $stationary, 'lane_duration_millis' => 22_000]);
    }

    public function test_fastest_and_slow_food_go_to_the_team_with_the_single_fastest_and_slowest_stop(): void
    {
        [$red, $blue, [$redOne, $blueOne, $redTwo, $blueTwo]] = $this->pitStopSeason();
        $this->stop($redOne, 2_300);
        $this->stop($blueOne, 2_100);
        $this->stop($blueOne, 1_200, 30);                          // below 1.5 s: a glitch
        $this->stop($redTwo, 9_400);
        $this->stop($blueTwo, 3_000);

        $this->runSeason(2025);

        $fastest = $this->awards('fastest_food')->sole();
        $this->assertSame([RecipientType::Team, $blue->id, '2.1 s'], [$fastest->recipient_type, $fastest->season_team_id, $fastest->detail]);
        $slowest = $this->awards('slow_food')->sole();
        $this->assertSame([$red->id, '9.4 s'], [$slowest->season_team_id, $slowest->detail]);
    }

    public function test_slow_food_leaves_out_a_stop_the_car_never_rejoined_from(): void
    {
        [$red, $blue, [$redOne, $blueOne]] = $this->pitStopSeason();
        DriverEntry::query()->whereKey($redOne->id)->first()->result->update(['laps_completed' => 30, 'position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Gearbox']);
        $this->stop($redOne, 40_000, 30);
        $this->stop($blueOne, 4_000);

        $this->runSeason(2025);

        $this->assertSame([$blue->id], $this->awards('slow_food')->pluck('season_team_id')->all());
    }

    public function test_the_food_awards_wait_for_a_season_whose_races_are_measured_well_enough(): void
    {
        [, , [$redOne, $blueOne, $redTwo]] = $this->pitStopSeason();
        $this->stop($redOne, 2_300);
        $this->stop($blueOne, 2_500);
        $this->stop($redTwo, 2_400);
        $this->stop($redTwo, null, 35);                            // the second race: one of two visits measured

        $this->runSeason(2025);

        $this->assertCount(0, $this->awards('fastest_food'));
        $this->assertCount(0, $this->awards('slow_food'));
    }

    /**
     * Ten races of 2025 with a red and a blue car, each with one timed stop
     * (red 3.0 s, blue 2.6 s); the races named get an extra untimed visit
     * for both cars, so only a third of their visits is timed.
     *
     * @param  list<int>  $poorlyTimed  Round numbers
     * @return array{0: SeasonTeam, 1: SeasonTeam, 2: array<int, array{0: DriverEntry, 1: DriverEntry}>}
     */
    private function tenRaces(array $poorlyTimed): array
    {
        $red = SeasonTeam::factory()->create(['season_id' => Season::firstOrCreate(['year' => 2025])->id]);
        $blue = SeasonTeam::factory()->create(['season_id' => $red->season_id]);
        $cars = [];

        foreach (range(1, 9) as $roundNumber) {
            $race = $this->race(2025, $roundNumber);
            $cars[$roundNumber] = [$this->finish($race, 1, Driver::factory()->create(), 1, team: $red), $this->finish($race, 2, Driver::factory()->create(), 2, team: $blue)];
        }

        // The tenth race, in October, so the round numbers stay single digits in the dates.
        $race = $this->raceSession(SessionType::Race, $this->round(2025, 10, ['date' => '2025-10-01']));
        $race->update(['achievements_checked_at' => now()]);
        $cars[10] = [$this->finish($race, 1, Driver::factory()->create(), 1, team: $red), $this->finish($race, 2, Driver::factory()->create(), 2, team: $blue)];

        foreach ($cars as $roundNumber => [$redCar, $blueCar]) {
            $this->stop($redCar, 3_000);
            $this->stop($blueCar, 2_600);

            if (in_array($roundNumber, $poorlyTimed, true)) {
                $this->stop($redCar, null, 35);
                $this->stop($blueCar, null, 35);
            }
        }

        return [$red, $blue, $cars];
    }

    public function test_a_race_without_full_timing_data_is_left_out_for_every_team_and_the_detail_says_so(): void
    {
        [$red, $blue, $cars] = $this->tenRaces([3]);
        // In the poorly timed race red had the quickest and the slowest stop of the season.
        $this->stop($cars[3][0], 1_900, 10);
        $this->stop($cars[3][0], 12_000, 40);

        $this->runSeason(2025);

        $fastest = $this->awards('fastest_food')->sole();
        $this->assertSame([$blue->id, '2.6 s · 1 race without full timing data'], [$fastest->season_team_id, $fastest->detail]);
        $this->assertStringContainsString('Left out without full timing data: Round 3 Race.', $fastest->evidence['note']);
        $slowest = $this->awards('slow_food')->sole();
        $this->assertSame([$red->id, '3.0 s · 1 race without full timing data'], [$slowest->season_team_id, $slowest->detail]);
    }

    public function test_a_season_needs_ninety_percent_of_its_races_fully_timed(): void
    {
        $this->tenRaces([3, 7]);

        $this->runSeason(2025);

        $this->assertCount(0, $this->awards('fastest_food'), '8 of 10 races timed: below 90%.');
        $this->assertCount(0, $this->awards('slow_food'));
    }

    public function test_perfect_attendance_needs_points_in_every_grand_prix(): void
    {
        [$alice, $bob, $carol] = $this->drivers();
        $first = $this->race(2024, 1);
        $this->finish($first, 1, $alice, 1);
        $this->finish($first, 2, $bob, 2);
        $final = $this->race(2024, 2);
        $this->finish($final, 1, $alice, 3);
        $this->finish($final, 2, $bob, 12, ['points' => 0]);
        // Carol missed the first round.
        $this->finish($final, 3, $carol, 1);

        $this->runSeason(2024);

        $this->assertSame([$alice->id], $this->winners('perfect_attendance'));
        $this->assertSame('points in all 2 Grands Prix', $this->awards('perfect_attendance')->sole()->detail);
    }

    public function test_like_a_metronome_needs_every_flag_and_a_classified_retirement_breaks_it(): void
    {
        [$alice, $bob, $carol] = $this->drivers();
        $first = $this->race(2024, 1);
        $this->finish($first, 1, $alice, 1);
        $this->finish($first, 2, $bob, 2, ['status' => '+1 Lap']);
        $this->finish($first, 3, $carol, 3);
        $final = $this->race(2024, 2);
        $this->finish($final, 1, $alice, 1);
        $this->finish($final, 2, $bob, 2);
        // Classified at 90% distance with a failure: still a retirement.
        $this->finish($final, 3, $carol, 14, ['status' => 'Engine']);

        $this->runSeason(2024);

        $this->assertSame([$alice->id, $bob->id], $this->winners('like_a_metronome'));
    }

    public function test_homebody_leaves_out_sensitive_weekends_and_the_oof_side_is_absent_before_2023(): void
    {
        [$alice, $bob] = $this->drivers();
        $first = $this->race(2024, 1);
        $this->finish($first, 1, $alice, 0, $this->retirement('Accident'));
        $this->finish($first, 2, $bob, 1);
        $final = $this->race(2024, 2);
        $this->finish($final, 1, $alice, 0, $this->retirement());
        $this->finish($final, 2, $bob, 0, $this->retirement('Gearbox'));
        MomentSensitivity::factory()->create(['round_id' => $first->round_id]);

        $this->runSeason(2024);
        $this->assertSame([$alice->id, $bob->id], $this->winners('homebody'));

        $old = $this->race(2022, 1);
        $this->finish($old, 1, $alice, 0, $this->retirement());
        $this->runSeason(2022);
        $this->assertFalse(AchievementAward::query()->whereRelation('achievement', 'key', 'homebody')->whereRelation('season', 'year', 2022)->exists());
    }

    public function test_usual_suspect_counts_penalties_in_races_and_qualifying(): void
    {
        [$alice, $bob] = $this->drivers();
        $race = $this->race(2024, 1);
        $this->finish($race, 4, $alice, 3);
        $this->finish($race, 81, $bob, 4);
        $qualifying = $this->raceSession(SessionType::Qualifying, $race->round);
        $this->car($qualifying, 4, null, $alice);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 4 (NOR) - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: 10 SECOND TIME PENALTY FOR CAR 81 (PIA) - SPEEDING IN THE PIT LANE');
        $this->message($qualifying, 'FIA STEWARDS: 3 PLACE GRID PENALTY FOR CAR 4 (NOR) - IMPEDING');

        $this->runSeason(2024);

        $this->assertSame([$alice->id], $this->winners('usual_suspect'));
        $this->assertSame('2 penalties', $this->awards('usual_suspect')->sole()->detail);
    }

    public function test_best_in_show_is_most_races_lapped_with_laps_down_as_the_tie_break(): void
    {
        [$alice, $bob, $carol] = $this->drivers();

        foreach ([1, 2] as $roundNumber) {
            $race = $this->race(2024, $roundNumber);
            $this->finish($race, 9, Driver::factory()->create(), 1);
            $this->finish($race, 1, $alice, 2, ['status' => '+1 Lap']);
            $this->finish($race, 2, $bob, 3, ['status' => $roundNumber === 1 ? '+2 Laps' : '+1 Lap']);
            $this->finish($race, 3, $carol, 4, ['status' => $roundNumber === 1 ? '+4 Laps' : 'Finished']);
        }

        $this->runSeason(2024);

        $this->assertSame([$bob->id], $this->winners('best_in_show'));
        $this->assertSame('2 races lapped', $this->awards('best_in_show')->sole()->detail);
    }

    public function test_always_the_bridesmaid_is_the_best_in_the_standings_without_a_podium(): void
    {
        [$alice, $bob, $carol] = $this->drivers();
        $race = $this->race(2024, 1);
        $this->finish($race, 1, $alice, 1);
        $this->finish($race, 2, $bob, 4);
        $this->finish($race, 3, $carol, 5);

        foreach ([[$alice, 1, 25], [$bob, 2, 12], [$carol, 3, 10]] as [$driver, $position, $points]) {
            DriverStanding::create(['round_id' => $race->round_id, 'driver_id' => $driver->id, 'position' => $position, 'position_text' => (string) $position, 'points' => $points, 'wins' => 0]);
        }

        $this->runSeason(2024);

        $award = $this->awards('always_the_bridesmaid')->sole();
        $this->assertSame([$bob->id, 'P2 · 12 points'], [$award->driver_id, $award->detail]);
    }
}
