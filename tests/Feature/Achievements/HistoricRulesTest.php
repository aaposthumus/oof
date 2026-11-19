<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Facts;
use App\Achievements\Facts\Requirements;
use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The rules for 1950 to 2022 (Phase 11b, decisions D7 to D11 and Q2 to Q4).
 */
class HistoricRulesTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    private function race(int $year, array $round = []): RaceSession
    {
        return $this->raceSession(SessionType::Race, $this->round($year, 3, ['date' => "{$year}-05-05", ...$round]));
    }

    private function entryWith(RaceSession $race, int $car, array $attributes, ?Driver $driver = null): DriverEntry
    {
        $entry = $this->car($race, $car, driver: $driver);
        $this->resultFor($entry, ['laps_completed' => 60, 'status' => 'Finished', 'points' => 0, ...$attributes]);

        return $entry;
    }

    private function finisher(RaceSession $race, int $car, int $position, array $attributes = [], ?Driver $driver = null): DriverEntry
    {
        return $this->entryWith($race, $car, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'grid' => $position, ...$attributes], $driver);
    }

    /**
     * @return list<string>
     */
    private function awardedTo(DriverEntry $entry): array
    {
        return $this->awards()->where('driver_entry_id', $entry->id)->map(fn ($award) => $award->achievement->key)->sort()->values()->all();
    }

    public function test_the_indianapolis_500_gives_only_the_glory_of_the_official_record(): void
    {
        $race = $this->race(1955, ['name' => 'Indianapolis 500', 'jolpica_race_name' => 'Indianapolis 500']);
        $winner = $this->finisher($race, 1, 1, ['grid' => 14, 'points' => 8]);
        $lapped = $this->finisher($race, 2, 2, ['status' => '+1 Lap', 'points' => 6]);
        $retired = $this->entryWith($race, 3, ['position' => null, 'position_text' => 'R', 'classification_order' => 3, 'grid' => 2, 'status' => 'Engine']);
        $this->runEngine($race);

        $this->assertSame(['did_the_thing', 'first_time_for_everything'], $this->awardedTo($winner));
        $this->assertSame(['first_time_for_everything', 'silver_lining'], $this->awardedTo($lapped));
        $this->assertSame([], $this->awardedTo($retired));
    }

    public function test_a_driver_who_changed_cars_is_judged_on_the_best_one(): void
    {
        $race = $this->race(1956);
        $fangio = Driver::factory()->create();
        $shared = $this->finisher($race, 34, 1, ['points' => 4], $fangio);
        $teammate = $this->finisher($race, 35, 1, ['points' => 4]);
        $abandoned = $this->entryWith($race, 30, ['position' => null, 'position_text' => 'R', 'classification_order' => 9, 'grid' => 1, 'status' => 'Fuel pump', 'laps_completed' => 22], $fangio);
        $this->runEngine($race);

        $this->assertContains('did_the_thing', $this->awardedTo($shared));
        $this->assertContains('did_the_thing', $this->awardedTo($teammate));
        $this->assertSame([], $this->awardedTo($abandoned));
    }

    public function test_before_2023_a_plain_withdrawal_gets_nothing_and_a_failure_before_the_start_is_a_non_start(): void
    {
        $race = $this->race(1990);
        $this->finisher($race, 1, 1);
        $withdrew = $this->entryWith($race, 2, ['position' => null, 'position_text' => 'W', 'classification_order' => 2, 'grid' => 0, 'status' => 'Withdrew', 'laps_completed' => 0]);
        $broke = $this->entryWith($race, 3, ['position' => null, 'position_text' => 'W', 'classification_order' => 3, 'grid' => 5, 'status' => 'Engine', 'laps_completed' => 0]);
        $this->runEngine($race);

        $this->assertSame([], $this->awardedTo($withdrew));
        $this->assertSame(['forgot_the_keys'], $this->awardedTo($broke));
    }

    public function test_from_2023_a_plain_withdrawal_is_still_a_non_start(): void
    {
        $race = $this->race(2024);
        $this->finisher($race, 1, 1);
        $withdrew = $this->entryWith($race, 2, ['position' => null, 'position_text' => 'W', 'classification_order' => 2, 'grid' => 0, 'status' => 'Withdrew', 'laps_completed' => 0]);

        $this->assertSame([$withdrew->id], $this->detect('forgot_the_keys', $race)->pluck('recipient.entryId')->all());
    }

    public function test_a_classified_car_is_never_a_non_start_whatever_its_status(): void
    {
        $race = $this->race(2013);
        $this->finisher($race, 1, 1);
        $this->finisher($race, 2, 17, ['status' => 'Withdrew', 'laps_completed' => 54]);

        $this->assertCount(0, $this->detect('forgot_the_keys', $race));
    }

    public function test_without_qualifying_data_grid_1_is_the_pole(): void
    {
        $earlier = $this->race(1979);
        $this->finisher($earlier, 1, 1, ['points' => 9]);
        $race = $this->raceSession(SessionType::Race, $this->round(1980, 4, ['date' => '1980-05-05']));
        $poleman = $this->finisher($race, 7, 1, ['points' => 9]);
        $this->finisher($race, 8, 2, ['points' => 6]);

        $this->assertSame([$poleman->id], $this->detect('first_time_for_everything', $race)->where('instanceKey', 'first_pole')->pluck('recipient.entryId')->all());
        $this->assertSame($poleman->id, app(Facts::class)->of($this->data($race), 'qualifying_order')->first(fn (Fact $fact) => $fact->get('position') === 1)?->entryId);

        $this->runEngine($race);
        $this->assertSame('×2 · First win · First pole', $this->awards('first_time_for_everything')->firstWhere('driver_entry_id', $poleman->id)->detail);
    }

    public function test_with_qualifying_data_the_race_gives_no_pole(): void
    {
        $race = $this->race(2024);
        $poleman = $this->finisher($race, 7, 1);
        $qualifying = $this->raceSession(SessionType::Qualifying, $race->round);
        $this->qualifying($this->car($qualifying, 7, driver: $poleman->driver), 1);

        $this->assertCount(0, app(Facts::class)->of($this->data($race), 'qualifying_order'));
        $this->assertCount(1, app(Facts::class)->of($this->data($qualifying), 'qualifying_order'));
    }

    public function test_nearly_famous_is_the_first_place_outside_the_points_of_the_era(): void
    {
        $race = $this->race(1955);
        $sixth = $this->finisher($race, 6, 6);
        $this->finisher($race, 16, 6, ['points' => 1]); // the fastest-lap point
        $this->finisher($race, 11, 11);
        $this->assertSame([$sixth->id], $this->detect('nearly_famous', $race)->pluck('recipient.entryId')->all());

        $race = $this->raceSession(SessionType::Race, $this->round(2005, 4, ['date' => '2005-05-05']));
        $ninth = $this->finisher($race, 9, 9);
        $this->finisher($race, 10, 11);
        $this->assertSame([$ninth->id], $this->detect('nearly_famous', $race)->pluck('recipient.entryId')->all());
    }

    public function test_to_the_rescue_starts_in_1981(): void
    {
        $requirements = app(Requirements::class);

        foreach ([1975 => false, 1981 => true] as $year => $allowed) {
            $race = $this->race($year);
            $this->finisher($race, 1, 1);
            $this->assertSame($allowed, $requirements->allows('to_the_rescue', $this->data($race)));
        }
    }
}
