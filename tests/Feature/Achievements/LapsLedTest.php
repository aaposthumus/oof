<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Support\LapsLed;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Models\DriverEntry;
use App\Models\LapTime;
use App\Models\Position;
use App\Models\RaceSession;
use App\Models\SessionLap;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * Laps led (Phase 11b, decision Q1): Jolpica's lap times are the source of
 * truth, counted the standard way; OpenF1 stands in until they are in, and
 * Led Astray and Clean Sweep wait for them.
 */
class LapsLedTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private function finisher(RaceSession $race, int $car, int $position, int $laps = 3, array $attributes = []): DriverEntry
    {
        $entry = $this->car($race, $car);
        $this->resultFor($entry, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'grid' => $position, 'laps_completed' => $laps, ...$attributes]);

        return $entry;
    }

    /**
     * Jolpica lap times: the position at the end of each lap, and its time.
     *
     * @param  array<int, int>  $positions  By lap
     * @param  array<int, int>  $times  By lap, in milliseconds
     */
    private function jolpicaLaps(DriverEntry $entry, array $positions, array $times = []): void
    {
        foreach ($positions as $lap => $position) {
            LapTime::create(['driver_entry_id' => $entry->id, 'lap' => $lap, 'position' => $position, 'time_millis' => $times[$lap] ?? 90_000]);
        }
    }

    /**
     * OpenF1 laps of three laps each (starting 20:01, 20:02 and 20:03): the
     * leader is P1 until the winner takes over at $switchAt.
     */
    private function openF1Race(RaceSession $race, DriverEntry $leader, DriverEntry $winner, string $switchAt = '2024-05-05 20:02:30'): void
    {
        foreach ([$leader, $winner] as $entry) {
            foreach ([1, 2, 3] as $lap) {
                SessionLap::factory()->create(['race_session_id' => $race->id, 'driver_number' => $entry->car_number, 'driver_entry_id' => $entry->id, 'lap_number' => $lap, 'started_at' => "2024-05-05 20:0{$lap}:0{$entry->car_number}"]);
            }
        }

        Position::factory()->create(['race_session_id' => $race->id, 'driver_number' => $leader->car_number, 'driver_entry_id' => $leader->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 1]);
        Position::factory()->create(['race_session_id' => $race->id, 'driver_number' => $winner->car_number, 'driver_entry_id' => $winner->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 2]);
        Position::factory()->create(['race_session_id' => $race->id, 'driver_number' => $leader->car_number, 'driver_entry_id' => $leader->id, 'recorded_at' => $switchAt, 'position' => 2]);
        Position::factory()->create(['race_session_id' => $race->id, 'driver_number' => $winner->car_number, 'driver_entry_id' => $winner->id, 'recorded_at' => $switchAt, 'position' => 1]);
    }

    public function test_jolpica_counts_the_last_lap_so_the_winner_is_not_tied_with_the_runner_up(): void
    {
        $race = $this->raceSession();
        $leader = $this->finisher($race, 1, 2);
        $winner = $this->finisher($race, 2, 1);
        $this->jolpicaLaps($leader, [1 => 1, 2 => 2, 3 => 2]);
        $this->jolpicaLaps($winner, [1 => 2, 2 => 1, 3 => 1]);

        $this->assertEquals([$leader->id => 1, $winner->id => 2], LapsLed::of($this->data($race)));
        $this->assertCount(0, $this->detect('led_astray', $race));
    }

    public function test_jolpica_wins_over_openf1_when_both_are_in(): void
    {
        $race = $this->raceSession();
        $leader = $this->finisher($race, 1, 2);
        $winner = $this->finisher($race, 2, 1);
        $this->openF1Race($race, $leader, $winner);
        $this->jolpicaLaps($leader, [1 => 2, 2 => 2, 3 => 2]);
        $this->jolpicaLaps($winner, [1 => 1, 2 => 1, 3 => 1]);

        $this->assertEquals([$winner->id => 3], LapsLed::of($this->data($race)));
    }

    public function test_openf1_stands_in_and_counts_the_last_lap_for_the_winner(): void
    {
        $race = $this->raceSession();
        $leader = $this->finisher($race, 1, 2);
        $winner = $this->finisher($race, 2, 1);
        $this->openF1Race($race, $leader, $winner);

        // P1 at the start of lap 2 (the end of lap 1) and of lap 3 (the end of lap 2), plus the last lap.
        $this->assertEquals([$leader->id => 1, $winner->id => 2], LapsLed::of($this->data($race)));
    }

    public function test_an_award_found_on_openf1_alone_waits_for_the_lap_times(): void
    {
        $this->catalogue();
        $race = $this->raceSession();
        $leader = $this->finisher($race, 1, 2);
        $winner = $this->finisher($race, 2, 1);
        // OpenF1: the runner-up is P1 at the start of laps 2 and 3 (2 laps), the winner takes the last lap (1).
        $this->openF1Race($race, $leader, $winner, switchAt: '2024-05-05 20:03:30');

        $this->runEngine($race);
        $award = $this->awards('led_astray')->sole();
        $this->assertSame(AwardStatus::Pending, $award->status);
        $this->assertSame(AwardReviewReason::AwaitingLapTimes, $award->review_reason);

        $this->jolpicaLaps($leader, [1 => 1, 2 => 1, 3 => 2]);
        $this->jolpicaLaps($winner, [1 => 2, 2 => 2, 3 => 1]);
        $this->runEngine($race);
        $this->assertSame(AwardStatus::Final, $this->awards('led_astray')->sole()->status);
        $this->assertNull($this->awards('led_astray')->sole()->review_reason);
    }

    public function test_clean_sweep_before_2004_takes_the_fastest_lap_from_the_lap_times(): void
    {
        $race = $this->raceSession(round: $this->round(1999));
        $winner = $this->finisher($race, 1, 1, attributes: ['grid' => 1, 'fastest_lap_rank' => null]);
        $second = $this->finisher($race, 2, 2, attributes: ['grid' => 2, 'fastest_lap_rank' => null]);
        $this->jolpicaLaps($winner, [1 => 1, 2 => 1, 3 => 1], [1 => 91_000, 2 => 89_500, 3 => 90_000]);
        $this->jolpicaLaps($second, [1 => 2, 2 => 2, 3 => 2], [1 => 91_500, 2 => 89_900, 3 => 90_100]);

        $this->assertSame([$winner->id], $this->detect('clean_sweep', $race)->pluck('recipient.entryId')->all());

        LapTime::query()->where('driver_entry_id', $second->id)->where('lap', 3)->update(['time_millis' => 89_000]);
        $this->assertCount(0, $this->detect('clean_sweep', $race));
    }
}
