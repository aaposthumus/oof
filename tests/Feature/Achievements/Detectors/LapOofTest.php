<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Achievements\Rules\Sensitivity;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Enums\RecipientType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use App\Models\LapTime;
use App\Models\MomentSensitivity;
use App\Models\Position;
use App\Models\RaceSession;
use App\Models\SeasonTeam;
use App\Models\SessionLap;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The OOF achievements of Phase 12d that read the running order per lap:
 * Bubble Burst (retired while leading) and Dropped the Baton (a team's
 * one-two lost in the final laps, judged on the road).
 */
class LapOofTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    private RaceSession $race;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
        $this->race = $this->raceSession();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function entered(int $number, array $result, ?SeasonTeam $team = null): DriverEntry
    {
        $entry = $this->car($this->race, $number, $team);
        $this->resultFor($entry, ['grid' => $number, 'classification_order' => $number, 'status' => 'Finished', ...$result]);

        return $entry;
    }

    private function retired(int $number, int $laps, string $status = 'Engine'): DriverEntry
    {
        return $this->entered($number, ['position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => $status, 'laps_completed' => $laps]);
    }

    private function finished(int $number, int $position, int $laps, ?SeasonTeam $team = null): DriverEntry
    {
        return $this->entered($number, ['position' => $position, 'position_text' => (string) $position, 'classification_order' => $position, 'laps_completed' => $laps], $team);
    }

    /**
     * @param  array<int, int>  $positions  By lap
     */
    private function laps(DriverEntry $entry, array $positions): void
    {
        foreach ($positions as $lap => $position) {
            LapTime::create(['driver_entry_id' => $entry->id, 'lap' => $lap, 'position' => $position, 'time_millis' => 90_000]);
        }
    }

    /**
     * @return list<string>
     */
    private function storedKeys(): array
    {
        return $this->awards()->map(fn (AchievementAward $award) => $award->achievement->key)->unique()->sort()->values()->all();
    }

    // Bubble Burst

    public function test_bubble_burst_counts_a_car_that_slowed_on_its_last_lap_and_replaces_home_before_dinner(): void
    {
        $leader = $this->retired(16, 27, 'Turbo');
        $this->laps($leader, [25 => 1, 26 => 1, 27 => 4]);
        $winner = $this->finished(1, 1, 30);
        $this->laps($winner, [25 => 2, 26 => 2, 27 => 1, 28 => 1, 29 => 1, 30 => 1]);

        $this->runEngine($this->race);

        $award = $this->awards('bubble_burst')->sole();
        $this->assertSame([$leader->id, 'lap 27 · Turbo', AwardStatus::Final], [$award->driver_entry_id, $award->detail, $award->status]);
        $this->assertNotContains('home_before_dinner', $this->storedKeys());
        $this->assertSame(50, $award->points);
    }

    public function test_a_leader_passed_before_retiring_gets_home_before_dinner_not_bubble_burst(): void
    {
        $passed = $this->retired(16, 12);
        $this->laps($passed, [10 => 1, 11 => 2, 12 => 2]);

        $this->runEngine($this->race);

        $this->assertContains('home_before_dinner', $this->storedKeys());
        $this->assertNotContains('bubble_burst', $this->storedKeys());
    }

    public function test_a_lap_one_retirement_from_pole_is_early_lunch_only(): void
    {
        $this->retired(1, 0, 'Collision');

        $this->runEngine($this->race);

        $this->assertContains('early_lunch', $this->storedKeys());
        $this->assertNotContains('bubble_burst', $this->storedKeys());
    }

    public function test_bubble_burst_is_withheld_on_a_sensitive_weekend(): void
    {
        $leader = $this->retired(16, 27, 'Accident');
        $this->laps($leader, [26 => 1, 27 => 1]);
        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);

        $this->runEngine($this->race);

        $this->assertNotContains('bubble_burst', $this->storedKeys());
    }

    public function test_bubble_burst_waits_for_lap_times_while_openf1_stands_in(): void
    {
        $leader = $this->retired(16, 2);

        foreach ([1, 2] as $lap) {
            SessionLap::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 16, 'driver_entry_id' => $leader->id, 'lap_number' => $lap, 'started_at' => "2024-05-05 20:0{$lap}:00", 'duration_millis' => 60_000]);
        }

        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 16, 'driver_entry_id' => $leader->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => 1]);

        $this->runEngine($this->race);

        $award = $this->awards('bubble_burst')->sole();
        $this->assertSame([AwardStatus::Pending, AwardReviewReason::AwaitingLapTimes], [$award->status, $award->review_reason]);
    }

    // Dropped the Baton

    /**
     * Red runs one-two until $lostOn, when car 11 falls to $fallsTo; blue's car 4 is third until then.
     *
     * @return array{0: SeasonTeam, 1: SeasonTeam}
     */
    private function oneTwoLostOn(int $lostOn, int $fallsTo, int $laps = 50): array
    {
        $red = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $blue = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $winner = $this->finished(1, 1, $laps, $red);
        $faller = $this->finished(11, $fallsTo, $laps, $red);
        $third = $this->finished(4, 2, $laps, $blue);
        $range = range($laps - 7, $laps);
        $this->laps($winner, array_fill_keys($range, 1));
        $this->laps($faller, array_combine($range, array_map(fn (int $lap) => $lap < $lostOn ? 2 : $fallsTo, $range)));
        $this->laps($third, array_combine($range, array_map(fn (int $lap) => $lap < $lostOn ? 3 : 2, $range)));

        return [$red, $blue];
    }

    public function test_dropped_the_baton_is_a_one_two_lost_on_the_last_lap_for_the_team(): void
    {
        [$red] = $this->oneTwoLostOn(50, 3);

        $this->runEngine($this->race);

        $award = $this->awards('dropped_the_baton')->sole();
        $this->assertSame([RecipientType::Team, $red->id, null], [$award->recipient_type, $award->season_team_id, $award->driver_id]);
        $this->assertSame('1-2 on lap 49 of 50 · P1 and P3', $award->detail);
        $this->assertSame(100, $award->points);
    }

    public function test_a_one_two_lost_before_the_final_laps_is_no_dropped_baton(): void
    {
        $this->oneTwoLostOn(40, 3);

        $this->runEngine($this->race);

        $this->assertCount(0, $this->awards('dropped_the_baton'));
    }

    public function test_teammates_swapping_places_on_the_last_lap_still_finish_one_two(): void
    {
        $red = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $first = $this->finished(1, 2, 50, $red);
        $second = $this->finished(11, 1, 50, $red);
        $this->laps($first, [...array_fill_keys(range(44, 49), 1), 50 => 2]);
        $this->laps($second, [...array_fill_keys(range(44, 49), 2), 50 => 1]);

        $this->runEngine($this->race);

        $this->assertCount(0, $this->awards('dropped_the_baton'));
    }

    public function test_a_disqualification_after_the_race_is_no_dropped_baton(): void
    {
        $red = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $winner = $this->finished(1, 1, 50, $red);
        $disqualified = $this->entered(11, ['position' => null, 'position_text' => 'D', 'is_classified' => false, 'status' => 'Disqualified', 'laps_completed' => 50], $red);
        $this->laps($winner, array_fill_keys(range(44, 50), 1));
        $this->laps($disqualified, array_fill_keys(range(44, 50), 2));

        $this->runEngine($this->race);

        $this->assertCount(0, $this->awards('dropped_the_baton'));
    }

    public function test_dropped_the_baton_is_withheld_on_a_sensitive_weekend(): void
    {
        $this->oneTwoLostOn(50, 3);
        MomentSensitivity::factory()->create(['round_id' => $this->race->round_id]);

        $this->runEngine($this->race);

        $this->assertCount(0, $this->awards('dropped_the_baton'));
    }

    public function test_a_driver_block_on_either_car_holds_back_the_team_award(): void
    {
        // Not withheld here, so only the block can hold it back.
        Achievement::where('key', 'dropped_the_baton')->update(['withheld_on_sensitive_weekends' => false]);
        [$red] = $this->oneTwoLostOn(50, 3);
        $faller = DriverEntry::query()->where('race_session_id', $this->race->id)->where('car_number', 11)->sole();
        MomentSensitivity::factory()->blocked()->create(['round_id' => $this->race->round_id, 'driver_id' => $faller->driver_id]);

        $this->runEngine($this->race);

        $this->assertCount(0, $this->awards('dropped_the_baton'));
        $this->assertSame([$red->id], Sensitivity::forRound($this->race->round_id)['blocked_season_teams']);
    }

    public function test_dropped_the_baton_waits_for_lap_times_while_openf1_stands_in(): void
    {
        $red = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $blue = SeasonTeam::factory()->create(['season_id' => $this->race->round->season_id]);
        $cars = [$this->finished(1, 1, 3, $red), $this->finished(11, 3, 3, $red), $this->finished(4, 2, 3, $blue)];

        foreach ($cars as $car) {
            foreach ([1, 2, 3] as $lap) {
                SessionLap::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => $car->car_number, 'driver_entry_id' => $car->id, 'lap_number' => $lap, 'started_at' => "2024-05-05 20:0{$lap}:00", 'duration_millis' => 60_000]);
            }
        }

        foreach ([[$cars[0], 1], [$cars[1], 2], [$cars[2], 3]] as [$car, $position]) {
            Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => $car->car_number, 'driver_entry_id' => $car->id, 'recorded_at' => '2024-05-05 19:55:00', 'position' => $position]);
        }

        // Blue's car passes red's second car during the last lap.
        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 11, 'driver_entry_id' => $cars[1]->id, 'recorded_at' => '2024-05-05 20:03:30', 'position' => 3]);
        Position::factory()->create(['race_session_id' => $this->race->id, 'driver_number' => 4, 'driver_entry_id' => $cars[2]->id, 'recorded_at' => '2024-05-05 20:03:30', 'position' => 2]);

        $this->runEngine($this->race);

        $award = $this->awards('dropped_the_baton')->sole();
        $this->assertSame([AwardStatus::Pending, AwardReviewReason::AwaitingLapTimes], [$award->status, $award->review_reason]);
    }
}
