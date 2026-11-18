<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\Position;
use App\Models\PracticeResult;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\SeasonTeam;
use App\Models\SessionLap;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The 2027 pack: the nine new achievements and Zoomies (Built Different is
 * manual; see ManualAwardTest).
 */
class Pack2027DetectorsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private RaceSession $race;

    protected function setUp(): void
    {
        parent::setUp();

        $this->race = $this->raceSession();
    }

    /**
     * A car in the race with a result, starting where it finishes unless a grid is given.
     */
    private function finisher(int $car, int $position, array $attributes = [], ?SeasonTeam $team = null, ?Driver $driver = null, ?RaceSession $race = null): DriverEntry
    {
        $entry = $this->car($race ?? $this->race, $car, $team, $driver);
        $this->resultFor($entry, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'grid' => $position, 'laps_completed' => 57, ...$attributes]);

        return $entry;
    }

    /**
     * A 20-car qualifying in the race's round, P16-P20 out in Q1; teams by position.
     *
     * @param  array<int, SeasonTeam>  $teams
     * @return array<int, DriverEntry> By position
     */
    private function qualifyingField(array $teams = [], ?Round $round = null): array
    {
        $qualifying = $this->raceSession(SessionType::Qualifying, $round ?? $this->race->round);

        return collect(range(1, 20))->mapWithKeys(function (int $position) use ($qualifying, $teams) {
            $entry = $this->car($qualifying, $position + 1, $teams[$position] ?? null);
            $this->qualifying($entry, $position, 90_000 + $position * 100, $position <= 15 ? 89_000 : null, $position <= 10 ? 88_000 : null);

            return [$position => $entry];
        })->all();
    }

    public function test_nearly_famous_is_a_classified_p11(): void
    {
        $this->finisher(1, 10);
        $eleventh = $this->finisher(2, 11);
        $this->finisher(3, 12);

        $this->assertSame([$eleventh->id], $this->detect('nearly_famous', $this->race)->pluck('recipient.entryId')->all());
    }

    public function test_peaked_too_soon_is_pole_without_a_podium(): void
    {
        $pole = $this->finisher(1, 4);
        $this->finisher(2, 1);
        $qualifying = $this->raceSession(SessionType::Qualifying, $this->race->round);
        $this->qualifying($this->car($qualifying, 1, null, $pole->driver), 1);

        $this->assertSame('from pole to P4', $this->detect('peaked_too_soon', $this->race)->sole()->detail);

        $pole->result->update(['position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Engine']);
        $this->assertSame('from pole to Engine', $this->detect('peaked_too_soon', $this->race)->sole()->detail);

        $pole->result->update(['position' => 3, 'position_text' => '3', 'is_classified' => true, 'status' => 'Finished']);
        $this->assertCount(0, $this->detect('peaked_too_soon', $this->race));
    }

    public function test_led_astray_is_the_most_laps_led_without_the_win(): void
    {
        $leader = $this->finisher(1, 2, ['laps_completed' => 4]);
        $winner = $this->finisher(2, 1, ['laps_completed' => 4]);

        foreach ([$leader, $winner] as $entry) {
            foreach ([1, 2, 3, 4] as $lap) {
                SessionLap::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => $entry->car_number, 'driver_entry_id' => $entry->id, 'lap_number' => $lap, 'started_at' => "2024-05-05 20:0{$lap}:0{$entry->car_number}"]);
            }
        }

        // The leader leads at the end of laps 1 and 2, the winner at the end of lap 3.
        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 1, 'driver_entry_id' => $leader->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 1]);
        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 2, 'driver_entry_id' => $winner->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 2]);
        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 1, 'driver_entry_id' => $leader->id, 'recorded_at' => '2024-05-05 20:03:30', 'position' => 2]);
        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 2, 'driver_entry_id' => $winner->id, 'recorded_at' => '2024-05-05 20:03:30', 'position' => 1]);

        $detection = $this->detect('led_astray', $this->race)->sole();
        $this->assertSame($leader->id, $detection->recipient->entryId);
        $this->assertSame('2 laps led · P2', $detection->detail);
    }

    public function test_economy_class_is_a_top_four_team_on_form_out_in_q1(): void
    {
        $top = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 4, ['date' => '2024-04-21']));
        $this->resultFor($this->car($earlier, 1, $top), ['points' => 25]);

        // P16 is the top team, out in Q1; P15 is the top team too, but out in Q2; P17 has no form.
        $field = $this->qualifyingField([15 => $top, 16 => $top]);

        $detection = $this->detect('economy_class', $field[1]->raceSession)->sole();
        $this->assertSame($field[16]->id, $detection->recipient->entryId);
        $this->assertSame('P16 · team P1 on form', $detection->detail);
    }

    public function test_fools_gold_is_a_practice_topper_who_qualified_p11_or_lower_once_per_weekend(): void
    {
        $field = $this->qualifyingField();
        $practice1 = $this->raceSession(SessionType::Practice1, $this->race->round);
        $practice3 = $this->raceSession(SessionType::Practice3, $this->race->round);
        // P12 in qualifying topped FP1 and FP3; P5 topped nothing that counts.
        PracticeResult::factory()->for($this->car($practice1, 13, null, $field[12]->driver))->create(['position' => 1]);
        PracticeResult::factory()->for($this->car($practice3, 13, null, $field[12]->driver))->create(['position' => 1]);
        PracticeResult::factory()->for($this->car($practice1, 6, null, $field[5]->driver))->create(['position' => 2]);

        $detection = $this->detect('fools_gold', $field[1]->raceSession)->sole();
        $this->assertSame($field[12]->id, $detection->recipient->entryId);
        $this->assertSame('topped FP1 and FP3 · P12', $detection->detail);
    }

    public function test_sibling_rivalry_goes_to_both_teammates_and_replaces_the_incident(): void
    {
        $this->catalogue();
        $team = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $this->car($this->race, 81, $team, Driver::factory()->create(['family_name' => 'Piastri']));
        $this->car($this->race, 4, $team, Driver::factory()->create(['family_name' => 'Norris']));
        $this->car($this->race, 10, null, Driver::factory()->create(['family_name' => 'Gasly']));
        $this->message($this->race, 'TURN 1 INCIDENT INVOLVING CARS 81 (PIA) AND 4 (NOR) NOTED - CAUSING A COLLISION');
        $this->message($this->race, 'TURN 3 INCIDENT INVOLVING CARS 4 (NOR) AND 10 (GAS) NOTED - CAUSING A COLLISION');

        $this->runEngine($this->race);

        $this->assertSame(['with Norris', 'with Piastri'], $this->awards('sibling_rivalry')->pluck('detail')->sort()->values()->all());
        // Norris keeps his incident with Gasly; the teammates' incident is Sibling Rivalry only.
        $this->assertSame(['Gasly', 'Norris'], $this->awards('it_was_like_that_when_i_got_there')->map(fn ($award) => $award->driver->family_name)->sort()->values()->all());
    }

    public function test_first_time_for_everything_takes_the_most_specific_career_first(): void
    {
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 4, ['date' => '2024-04-21']));
        $scorer = $this->finisher(1, 5, ['points' => 10], race: $earlier);
        $winner = $this->finisher(2, 1, ['points' => 25], race: $earlier);

        $podium = $this->finisher(1, 2, ['points' => 18], driver: $scorer->driver);
        $this->finisher(2, 1, ['points' => 25], driver: $winner->driver);
        $rookie = $this->finisher(3, 10, ['points' => 1]);
        $firstWin = $this->finisher(4, 3, ['points' => 15]);
        $firstWin->result->update(['position' => 1, 'position_text' => '1']);

        $details = $this->detect('first_time_for_everything', $this->race)->mapWithKeys(fn ($detection) => [$detection->recipient->entryId => $detection->detail])->all();

        $this->assertSame([$podium->id => 'First podium', $rookie->id => 'First points', $firstWin->id => 'First win'], $details);
    }

    public function test_first_time_for_everything_counts_a_first_pole_in_qualifying(): void
    {
        $driver = Driver::factory()->create();
        $field = $this->qualifyingField();
        $field[1]->update(['driver_id' => $driver->id]);

        $this->assertSame('First pole', $this->detect('first_time_for_everything', $field[1]->raceSession)->sole()->detail);

        // An earlier pole in a round without a qualifying classification: grid 1.
        $earlier = $this->raceSession(SessionType::Race, $this->round(1990, 4, ['date' => '1990-04-21']));
        $this->finisher(1, 2, ['grid' => 1], driver: $driver, race: $earlier);

        $this->assertCount(0, $this->detect('first_time_for_everything', $field[1]->raceSession));
    }

    public function test_charging_rhino_gains_ten_places_from_the_grid_or_the_pit_lane(): void
    {
        foreach (range(1, 12) as $car) {
            $this->finisher($car, $car);
        }

        DriverEntry::query()->where('race_session_id', $this->race->id)->where('car_number', 4)->first()->result->update(['grid' => 15]);
        DriverEntry::query()->where('race_session_id', $this->race->id)->where('car_number', 2)->first()->result->update(['grid' => 0]);
        DriverEntry::query()->where('race_session_id', $this->race->id)->where('car_number', 3)->first()->result->update(['grid' => 12]);

        $this->assertSame(['from P15 to P4', 'from the pit lane to P2'], $this->detect('charging_rhino', $this->race)->pluck('detail')->sort()->values()->all());
    }

    public function test_zoomies_is_the_top_speed_trap_reading_shared_on_a_tie(): void
    {
        $entries = collect([1, 2, 3])->map(fn (int $car) => $this->finisher($car, $car));

        foreach ([[1, 330], [2, 334], [3, 334]] as [$car, $speed]) {
            SessionLap::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => $car, 'driver_entry_id' => $entries[$car - 1]->id, 'lap_number' => 5, 'st_speed' => $speed]);
        }

        $detections = $this->detect('zoomies', $this->race);

        $this->assertSame([$entries[1]->id, $entries[2]->id], $detections->pluck('recipient.entryId')->all());
        $this->assertSame('334 km/h', $detections->first()->detail);
    }
}
