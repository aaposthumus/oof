<?php

namespace Tests\Feature\Achievements;

use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use App\Models\SeasonTeam;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The pole from the grid follows the official record (decided by the owner
 * on 21 November 2026): a car swap in the race (D8) does not take it away,
 * and a shared car on grid 1 gives exactly one pole, to the driver
 * config/official_poles.php names. Early Bird and Plot Twist both read it.
 */
class OfficialPoleTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function entered(RaceSession $race, int $number, Driver $driver, array $result, ?SeasonTeam $team = null): DriverEntry
    {
        $entry = $this->car($race, $number, $team, $driver);
        $this->resultFor($entry, ['classification_order' => $number, 'status' => 'Finished', ...$result]);

        return $entry;
    }

    private function driver(string $jolpicaId): Driver
    {
        return Driver::factory()->create(['jolpica_driver_id' => $jolpicaId, 'family_name' => ucfirst($jolpicaId)]);
    }

    /**
     * @return list<int>
     */
    private function storedDrivers(string $key): array
    {
        return $this->awards($key)->pluck('driver_id')->all();
    }

    /**
     * Argentina 1956: Fangio took pole in car 30, retired it on lap 22, took
     * over Musso's car 34 and won in it.
     *
     * @return array{0: RaceSession, 1: DriverEntry, 2: DriverEntry, 3: Driver}
     */
    private function argentina1956(): array
    {
        $race = $this->raceSession(SessionType::Race, $this->round(1956, 1, ['name' => 'Argentine Grand Prix']));
        $fangio = $this->driver('fangio');
        $poleCar = $this->entered($race, 30, $fangio, ['grid' => 1, 'position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Fuel pump', 'laps_completed' => 22]);
        $winningCar = $this->entered($race, 34, $fangio, ['grid' => 8, 'position' => 1, 'position_text' => '1', 'laps_completed' => 98]);
        $this->entered($race, 34, $this->driver('musso'), ['grid' => 8, 'position' => 1, 'position_text' => '1', 'laps_completed' => 98]);
        $this->entered($race, 2, $this->driver('behra'), ['grid' => 2, 'position' => 2, 'position_text' => '2', 'laps_completed' => 98]);

        return [$race, $poleCar, $winningCar, $fangio];
    }

    public function test_a_car_swap_in_the_race_keeps_the_pole_with_the_car_it_was_earned_in(): void
    {
        [$race, $poleCar, $winningCar, $fangio] = $this->argentina1956();

        $this->assertContains($poleCar->id, $this->data($race)->setAsideEntryIds(), 'D8 still sets the left car aside for everything else.');

        $this->runEngine($race);

        $pole = $this->awards('early_bird')->sole();
        $this->assertSame([$fangio->id, $poleCar->id], [$pole->driver_id, $pole->driver_entry_id]);
        $this->assertContains($fangio->id, $this->storedDrivers('did_the_thing'));
        $this->assertSame($winningCar->id, $this->awards('did_the_thing')->firstWhere('driver_id', $fangio->id)->driver_entry_id);
        $this->assertCount(0, $this->awards('home_before_dinner'), 'The left car still gets no retirement.');
    }

    /**
     * Argentina 1955: González took pole in car 12, which Farina and
     * Trintignant shared; Jolpica lists all three on grid 1.
     *
     * @return array{0: RaceSession, 1: Driver}
     */
    private function argentina1955(): array
    {
        $race = $this->raceSession(SessionType::Race, $this->round(1955, 1, ['name' => 'Argentine Grand Prix']));
        $gonzalez = $this->driver('gonzalez');

        foreach ([$this->driver('farina'), $gonzalez, $this->driver('trintignant')] as $index => $driver) {
            $this->entered($race, 12, $driver, ['grid' => 1, 'position' => 2, 'position_text' => '2', 'classification_order' => 2 + $index, 'laps_completed' => 96]);
        }

        $this->entered($race, 2, $this->driver('fangio'), ['grid' => 3, 'position' => 1, 'position_text' => '1', 'laps_completed' => 96]);

        return [$race, $gonzalez];
    }

    public function test_a_shared_car_on_grid_1_gives_one_pole_to_the_driver_the_record_credits(): void
    {
        [$race, $gonzalez] = $this->argentina1955();

        $this->runEngine($race);

        $this->assertSame([$gonzalez->id], $this->storedDrivers('early_bird'));
    }

    public function test_several_drivers_on_grid_1_in_an_unlisted_round_give_no_pole(): void
    {
        [$race] = $this->argentina1955();
        config(['official_poles.rounds' => []]);

        $this->runEngine($race);

        $this->assertCount(0, $this->awards('early_bird'));
    }

    public function test_two_different_cars_on_grid_1_follow_the_list_too(): void
    {
        $race = $this->raceSession(SessionType::Race, $this->round(1952, 8, ['name' => 'Italian Grand Prix']));
        $ascari = $this->driver('ascari');
        $this->entered($race, 12, $ascari, ['grid' => 1, 'position' => 1, 'position_text' => '1', 'laps_completed' => 80]);
        $this->entered($race, 32, $this->driver('moss'), ['grid' => 1, 'position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Suspension', 'laps_completed' => 60]);

        $this->runEngine($race);

        $this->assertSame([$ascari->id], $this->storedDrivers('early_bird'));
    }

    public function test_plot_twist_follows_the_same_pole(): void
    {
        [$race, $poleCar, , $fangio] = $this->argentina1956();
        $earlier = $this->raceSession(SessionType::Race, $this->round(1955, 9));

        foreach ([8, 6, 4, 3] as $index => $points) {
            $this->entered($earlier, $index + 1, Driver::factory()->create(), ['grid' => $index + 1, 'position' => $index + 1, 'position_text' => (string) ($index + 1), 'points' => $points]);
        }

        $lastOnForm = SeasonTeam::factory()->create(['season_id' => $earlier->round->season_id, 'team_id' => $poleCar->seasonTeam->team_id]);
        $this->entered($earlier, 9, Driver::factory()->create(), ['grid' => 5, 'position' => 5, 'position_text' => '5', 'points' => 0], $lastOnForm);

        $this->runEngine($race);

        // Fangio's team is fifth on form, so his pole is a surprise and Plot Twist replaces Early Bird.
        $twist = $this->awards('plot_twist')->sole();
        $this->assertSame([$fangio->id, $poleCar->id], [$twist->driver_id, $twist->driver_entry_id]);
        $this->assertCount(0, $this->awards('early_bird'));
    }
}
