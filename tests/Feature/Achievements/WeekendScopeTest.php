<?php

namespace Tests\Feature\Achievements;

use App\Enums\AwardScope;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\DriverEntry;
use App\Models\PitLaneVisit;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\SessionLap;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class WeekendScopeTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    /**
     * Laps in a practice session for a car.
     */
    private function laps(RaceSession $practice, DriverEntry $entry, int $laps): void
    {
        foreach (range(1, $laps) as $lap) {
            SessionLap::factory()->create(['race_session_id' => $practice->id, 'driver_number' => $entry->car_number, 'driver_entry_id' => $entry->id, 'lap_number' => $lap]);
        }
    }

    /**
     * A weekend of FP1, FP2 and the race with two race drivers.
     *
     * @return array{0: Round, 1: array<string, RaceSession>, 2: array<int, array<string, DriverEntry>>}
     */
    private function weekend(): array
    {
        $round = $this->round();
        $sessions = [
            'fp1' => $this->raceSession(SessionType::Practice1, $round),
            'fp2' => $this->raceSession(SessionType::Practice2, $round),
            'race' => $this->raceSession(SessionType::Race, $round),
        ];
        $cars = [];

        foreach ([16, 55] as $number) {
            $race = $this->car($sessions['race'], $number);
            $cars[$number] = [
                'race' => $race,
                'fp1' => $this->car($sessions['fp1'], $number, null, $race->driver),
                'fp2' => $this->car($sessions['fp2'], $number, null, $race->driver),
            ];
        }

        return [$round, $sessions, $cars];
    }

    public function test_overtime_is_decided_once_per_weekend_over_the_laps_of_every_practice_session(): void
    {
        [$round, $sessions, $cars] = $this->weekend();
        // Car 55 tops each session alone, but car 16 drove more over the weekend.
        $this->laps($sessions['fp1'], $cars[16]['fp1'], 20);
        $this->laps($sessions['fp1'], $cars[55]['fp1'], 25);
        $this->laps($sessions['fp2'], $cars[16]['fp2'], 22);
        $this->laps($sessions['fp2'], $cars[55]['fp2'], 10);

        $this->runRound($round);

        $award = $this->awards('overtime')->sole();
        $this->assertSame(AwardScope::Weekend, $award->scope);
        $this->assertSame("round:{$round->id}", $award->context_key);
        $this->assertSame('42 laps', $award->detail);
        // Anchored to the last practice session, on the car the driver drove in it.
        $this->assertSame($sessions['fp2']->id, $award->race_session_id);
        $this->assertSame($cars[16]['fp2']->id, $award->driver_entry_id);
        $this->assertSame($round->id, $award->round_id);
        $this->assertEquals([['driver' => $cars[16]['race']->driver->family_name, 'count' => 42], ['driver' => $cars[55]['race']->driver->family_name, 'count' => 35]], $award->evidence['rows']);
        $this->assertSame(AwardStatus::Final, $award->status);
    }

    public function test_a_re_run_updates_the_weekend_in_place(): void
    {
        [$round, $sessions, $cars] = $this->weekend();
        $this->laps($sessions['fp1'], $cars[16]['fp1'], 20);
        $this->runRound($round);
        $first = $this->awards('overtime')->sole();

        $this->laps($sessions['fp2'], $cars[16]['fp2'], 30);
        $stats = $this->runWeekend($round);

        $this->assertSame(1, $stats['overtime']['updated']);
        $this->assertSame($first->id, $this->awards('overtime')->sole()->id);
        $this->assertSame('50 laps', $this->awards('overtime')->sole()->detail);

        // Another driver on top is another award: the old one goes.
        $this->laps($sessions['fp2'], $cars[55]['fp2'], 60);
        $stats = $this->runWeekend($round);

        $this->assertSame(['found' => 1, 'created' => 1, 'updated' => 0, 'removed' => 1, 'pending' => 0], $stats['overtime']);
        $this->assertSame($cars[55]['race']->driver_id, $this->awards('overtime')->sole()->driver_id);
    }

    public function test_everyone_on_the_top_weekend_total_shares_overtime(): void
    {
        [$round, $sessions, $cars] = $this->weekend();
        $this->laps($sessions['fp1'], $cars[16]['fp1'], 20);
        $this->laps($sessions['fp2'], $cars[55]['fp2'], 20);

        $this->runRound($round);

        $shared = $this->awards('overtime');
        $this->assertEqualsCanonicalizing([$cars[16]['fp2']->id, $cars[55]['fp2']->id], $shared->pluck('driver_entry_id')->all());
        $this->assertSame(['20 laps'], $shared->pluck('detail')->unique()->values()->all());
    }

    public function test_everyone_on_the_top_weekend_total_shares_practice_makes_imperfect(): void
    {
        [$round, $sessions] = $this->weekend();

        foreach (['1:30.000', '1:30.100', '1:30.200'] as $time) {
            $this->message($sessions['fp1'], "CAR 16 (LEC) TIME {$time} DELETED - TRACK LIMITS AT TURN 4 LAP 3 13:10:00");
            $this->message($sessions['fp2'], "CAR 55 (SAI) TIME {$time} DELETED - TRACK LIMITS AT TURN 4 LAP 3 13:10:00");
        }

        $this->runRound($round);

        $this->assertCount(2, $this->awards('practice_makes_imperfect'));
        $this->assertSame(['3 deleted laps'], $this->awards('practice_makes_imperfect')->pluck('detail')->unique()->values()->all());
    }

    public function test_a_weekend_achievement_waits_until_its_deciding_session_has_ended(): void
    {
        [$round, $sessions, $cars] = $this->weekend();
        $this->laps($sessions['fp1'], $cars[16]['fp1'], 20);
        $duringFp2 = CarbonImmutable::parse('2024-05-05 21:00:00');

        $this->runWeekend($round, $duringFp2);
        $this->assertCount(0, $this->awards('overtime'));

        $this->runWeekend($round);
        $this->assertCount(1, $this->awards('overtime'));

        // Undecided again (as if FP2 had moved later): the stored award stays as it is.
        $this->runWeekend($round, $duringFp2);
        $this->assertCount(1, $this->awards('overtime'));
    }

    public function test_a_disabled_weekend_achievement_loses_its_awards_on_the_next_run(): void
    {
        [$round, $sessions, $cars] = $this->weekend();
        $this->laps($sessions['fp1'], $cars[16]['fp1'], 20);
        $this->runRound($round);

        Achievement::firstWhere('key', 'overtime')->update(['is_enabled' => false]);
        $stats = $this->runWeekend($round);

        $this->assertSame(1, $stats['overtime']['removed']);
        $this->assertCount(0, $this->awards('overtime'));
    }

    public function test_the_session_scope_no_longer_finds_a_weekend_achievement(): void
    {
        [, $sessions, $cars] = $this->weekend();
        $this->laps($sessions['fp1'], $cars[16]['fp1'], 20);

        $this->runEngine($sessions['fp1']);

        $this->assertCount(0, $this->awards('overtime'));
    }

    public function test_fine_dining_takes_the_slowest_stop_of_race_and_sprint_and_replaces_its_table_for_one(): void
    {
        $race = $this->raceSession();
        $sprint = $this->raceSession(SessionType::Sprint, $race->round);
        $car = $this->car($race, 44);
        $sprintCar = $this->car($sprint, 44, null, $car->driver);
        $raceStop = PitLaneVisit::factory()->create(['race_session_id' => $race->id, 'driver_number' => 44, 'driver_entry_id' => $car->id, 'entered_at' => '2024-05-05 20:20:00', 'stop_duration_millis' => 6_200, 'lane_duration_millis' => 25_000]);
        $sprintStop = PitLaneVisit::factory()->create(['race_session_id' => $sprint->id, 'driver_number' => 44, 'driver_entry_id' => $sprintCar->id, 'entered_at' => '2024-05-04 16:20:00', 'stop_duration_millis' => 8_100, 'lane_duration_millis' => 27_000]);

        $this->runRound($race->round);

        $fineDining = $this->awards('fine_dining')->sole();
        $this->assertSame("pit:{$sprintStop->id}", $fineDining->event_key);
        $this->assertSame('8.1 s', $fineDining->detail);
        $this->assertSame($race->id, $fineDining->race_session_id);
        $this->assertSame($car->id, $fineDining->driver_entry_id);
        // The sprint's slow stop is Fine Dining now; the race's slow stop stays a Table for One.
        $this->assertSame(["pit:{$raceStop->id}"], $this->awards('table_for_one')->pluck('event_key')->all());
    }

    public function test_nothing_to_see_here_counts_a_weekend_award(): void
    {
        [$round, $sessions, $cars] = $this->weekend();
        $earlier = $this->raceSession(SessionType::Race, $this->round(2024, 4));

        foreach ($cars as $number => $car) {
            $this->resultFor($car['race'], ['position' => $number === 16 ? 8 : 9, 'position_text' => $number === 16 ? '8' : '9', 'classification_order' => $number === 16 ? 8 : 9, 'grid' => $number === 16 ? 8 : 9]);
            $this->resultFor($this->car($earlier, $number, null, $car['race']->driver), ['position' => 5, 'position_text' => '5']);
        }

        $this->resultFor($this->car($sessions['race'], 99), ['position' => 20, 'position_text' => '20', 'classification_order' => 20, 'grid' => 20]);
        $this->laps($sessions['fp1'], $cars[16]['fp1'], 20);

        $this->runRound($round);

        $quiet = $this->awards('nothing_to_see_here')->sole();
        $this->assertSame($cars[55]['race']->id, $quiet->driver_entry_id);
        $this->assertSame(AwardScope::Weekend, $quiet->scope);
        $this->assertSame($sessions['race']->id, $quiet->race_session_id);
    }
}
