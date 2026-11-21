<?php

namespace Tests\Feature\Achievements;

use App\Achievements\CatalogueSync;
use App\Achievements\Support\RunningOrder;
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
 * The running order per lap (RunningOrder) and the retired_while_leading
 * predicate (Phase 12b), for Bubble Burst and Dropped the Baton. Proven with
 * an achievement that exists only in this test.
 */
class RetiredWhileLeadingTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        config(['achievement_catalogue.achievements.burst' => [
            ...config('achievement_catalogue.achievements.home_before_dinner'),
            'name' => 'Burst',
            'sessions' => ['race'],
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'match', 'fact' => 'result', 'where' => ['retired_while_leading']],
        ]]);
        app(CatalogueSync::class)->apply();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function carWithResult(RaceSession $race, int $number, array $result): DriverEntry
    {
        $entry = $this->car($race, $number);
        $this->resultFor($entry, ['grid' => $number, 'classification_order' => $number, ...$result]);

        return $entry;
    }

    private function retired(RaceSession $race, int $number, int $laps): DriverEntry
    {
        return $this->carWithResult($race, $number, ['position' => null, 'position_text' => 'R', 'status' => 'Engine', 'laps_completed' => $laps, 'is_classified' => false]);
    }

    /**
     * @param  array<int, int>  $positions  By lap
     */
    private function jolpicaLaps(DriverEntry $entry, array $positions): void
    {
        foreach ($positions as $lap => $position) {
            LapTime::create(['driver_entry_id' => $entry->id, 'lap' => $lap, 'position' => $position, 'time_millis' => 90_000]);
        }
    }

    public function test_the_running_order_comes_from_jolpicas_lap_times(): void
    {
        $race = $this->raceSession();
        $first = $this->retired($race, 1, 3);
        $second = $this->retired($race, 2, 3);
        $this->jolpicaLaps($first, [1 => 1, 2 => 2, 3 => 2]);
        $this->jolpicaLaps($second, [1 => 2, 2 => 1, 3 => 1]);
        $data = $this->data($race);

        $this->assertSame([1 => $second->id, 2 => $first->id], RunningOrder::orderAt($data, 2));
        $this->assertSame(1, RunningOrder::positionAt($data, $first->id, 1));
        $this->assertSame(3, RunningOrder::lastLap($data));
        $this->assertNull(RunningOrder::positionAt($data, $first->id, 4));
    }

    public function test_a_car_that_slowed_on_its_last_lap_still_retired_while_leading(): void
    {
        $race = $this->raceSession();
        $leader = $this->retired($race, 16, 27);
        $this->jolpicaLaps($leader, [25 => 1, 26 => 1, 27 => 4]);

        $this->assertSame($leader->id, $this->detect('burst', $race)->sole()->recipient->entryId);
    }

    public function test_a_car_passed_before_it_retired_was_not_leading(): void
    {
        $race = $this->raceSession();
        $passed = $this->retired($race, 16, 12);
        $this->jolpicaLaps($passed, [10 => 1, 11 => 2, 12 => 2]);

        $this->assertCount(0, $this->detect('burst', $race));
    }

    public function test_a_lap_one_retirement_or_a_leader_who_finished_is_not_a_bubble_burst(): void
    {
        $race = $this->raceSession();
        $this->retired($race, 1, 0);
        $winner = $this->carWithResult($race, 2, ['position' => 1, 'position_text' => '1', 'status' => 'Finished', 'laps_completed' => 3]);
        $this->jolpicaLaps($winner, [1 => 1, 2 => 1, 3 => 1]);

        $this->assertCount(0, $this->detect('burst', $race));
    }

    public function test_openf1_stands_in_until_the_lap_times_are_in_and_the_award_waits_for_them(): void
    {
        $race = $this->raceSession();
        $leader = $this->retired($race, 16, 2);

        foreach ([1, 2] as $lap) {
            SessionLap::factory()->create(['race_session_id' => $race->id, 'driver_number' => 16, 'driver_entry_id' => $leader->id, 'lap_number' => $lap, 'started_at' => "2024-05-05 20:0{$lap}:00", 'duration_millis' => 60_000]);
        }

        Position::factory()->create(['race_session_id' => $race->id, 'driver_number' => 16, 'driver_entry_id' => $leader->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 1]);

        $this->assertSame([1 => [$leader->id => 1], 2 => [$leader->id => 1]], RunningOrder::of($this->data($race)));
        $this->assertCount(1, $this->detect('burst', $race));

        $this->runEngine($race);
        $award = $this->awards('burst')->sole();
        $this->assertSame([AwardStatus::Pending, AwardReviewReason::AwaitingLapTimes], [$award->status, $award->review_reason]);
    }
}
